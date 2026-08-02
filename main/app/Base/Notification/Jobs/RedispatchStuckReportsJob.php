<?php

declare(strict_types=1);

namespace App\Base\Notification\Jobs;

use App\Base\Notification\Repository\NotificationReportRepository;
use App\Models\NotificationReport;
use App\Queue\Queue;
use App\Queue\QueueSettings;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class RedispatchStuckReportsJob extends Queue implements ShouldBeUnique
{
    /**
     * Таймаут выполнения.
     *
     * @var int
     */
    public $timeout = QueueSettings::LOW_PRIORITY_TIMEOUT;

    /**
     * Время жизни лока уникальности, секунды: не больше одной такой
     * джобы в очереди или в работе; лок истекает сам, если воркер
     * умер, не освободив его.
     *
     * @var int
     */
    public $uniqueFor = QueueSettings::LOW_PRIORITY_TIMEOUT;

    /**
     * RedispatchStuckReportsJob constructor.
     */
    public function __construct()
    {
        $this->onQueue(QueueSettings::LOW_PRIORITY_QUEUE);
    }

    /**
     * Watchdog отчётов: передиспатч зависших pending (потерянный
     * dispatch) и processing (убитый воркер).
     */
    public function handle(NotificationReportRepository $repository) : void
    {
        $threshold_minutes = (int) config('notification.watchdog.report_stuck_threshold_minutes');
        $batch_limit = (int) config('notification.watchdog.batch_limit');

        $repository->chunkStuck(
            Carbon::now()->subMinutes($threshold_minutes),
            $batch_limit,
            function (Collection $stuck) use ($repository) : void {
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
            }
        );
    }
}
