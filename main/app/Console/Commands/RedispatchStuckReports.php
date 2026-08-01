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
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:redispatch-stuck-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Передиспатч отчётов, зависших в статусах pending и processing';

    /**
     * Execute the console command.
     */
    public function handle(NotificationReportRepository $repository) : int
    {
        $threshold_minutes = (int) config('notification.watchdog.stuck_threshold_minutes');
        $batch_limit = (int) config('notification.watchdog.batch_limit');

        $total = 0;

        /**
         * Дренаж циклом до опустошения: touch() сдвигает updated_at,
         * поэтому каждая следующая выборка отдаёт только необработанное.
         */
        do {
            $stuck = $repository->getStuck(
                Carbon::now()->subMinutes($threshold_minutes),
                $batch_limit
            );

            $ids = $stuck->pluck('id')->all();

            /**
             * Зависшие processing возвращаются в pending одним условным
             * UPDATE — guard джобы генерации стартует только из pending.
             * Порог watchdog-а больше таймаута джобы, живой генерации
             * в этот момент нет; успевший завершиться отчёт условие
             * UPDATE не заденет, а его джоба идемпотентно пропустится.
             */
            $repository->resetToPendingAll($ids);

            /**
             * Массовый сдвиг updated_at одним UPDATE — повторный
             * передиспатч возможен не раньше следующего порога.
             */
            $repository->touchAll($ids);

            /** @var NotificationReport $report */
            foreach ($stuck as $report) {
                GenerateReportJob::dispatch($report->id);

                Log::warning("Отчёт #{$report->id} завис в генерации, джоба передиспатчена.");
            }

            $total += $stuck->count();
        } while ($stuck->count() === $batch_limit);

        $this->info($total > 0 ? "Передиспатчено отчётов: {$total}." : 'Зависших отчётов нет.');

        return self::SUCCESS;
    }
}
