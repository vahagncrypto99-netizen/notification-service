<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Base\Notification\Jobs\GenerateReportJob;
use App\Base\Notification\Repository\NotificationReportRepository;
use App\Models\NotificationReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RedispatchStuckReports extends Command
{
    /**
     * Сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'notification:redispatch-stuck-reports';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Передиспатч отчётов, зависших в статусах pending и processing';

    /**
     * Выполнение команды.
     */
    public function handle(NotificationReportRepository $repository) : int
    {
        $threshold_minutes = (int) config('notification.watchdog.report_stuck_threshold_minutes');
        $batch_limit = (int) config('notification.watchdog.batch_limit');

        $total = 0;

        /**
         * Дренаж циклом до опустошения: сдвиг updated_at выводит
         * обработанное из каждой следующей выборки.
         */
        do {
            $stuck = $repository->getStuck(
                Carbon::now()->subMinutes($threshold_minutes),
                $batch_limit
            );

            $ids = $stuck->pluck('id')->all();

            /**
             * Зависшие processing возвращаются в pending — генерация
             * стартует только из pending. Порог больше зазора между
             * bump-ами updated_at живой джобы (таймаут + backoff),
             * поэтому работающую генерацию watchdog не трогает.
             */
            $repository->resetToPendingAll($ids);

            /**
             * Сдвиг updated_at: повторный передиспатч возможен
             * не раньше следующего порога.
             */
            $repository->touchAll($ids);

            /** @var NotificationReport $report */
            foreach ($stuck as $report) {
                GenerateReportJob::dispatch($report->id);

                Log::warning('Отчёт завис в генерации, джоба передиспатчена.', [
                    'report_id' => $report->id,
                    'stuck_since' => $report->updated_at->toIso8601String(),
                ]);
            }

            $total += $stuck->count();
        } while ($stuck->count() === $batch_limit);

        $this->info($total > 0 ? "Передиспатчено отчётов: {$total}." : 'Зависших отчётов нет.');

        return self::SUCCESS;
    }
}
