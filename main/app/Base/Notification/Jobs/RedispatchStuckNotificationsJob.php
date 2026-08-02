<?php

declare(strict_types=1);

namespace App\Base\Notification\Jobs;

use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;
use App\Queue\Queue;
use App\Queue\QueueSettings;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class RedispatchStuckNotificationsJob extends Queue implements ShouldBeUnique
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
     * RedispatchStuckNotificationsJob constructor.
     */
    public function __construct()
    {
        $this->onQueue(QueueSettings::LOW_PRIORITY_QUEUE);
    }

    /**
     * Watchdog доставки: передиспатч уведомлений, зависших
     * в processing дольше порога (потерянные джобы).
     */
    public function handle(NotificationRepository $repository) : void
    {
        $threshold_minutes = (int) config('notification.watchdog.stuck_threshold_minutes');
        $batch_limit = (int) config('notification.watchdog.batch_limit');

        $repository->chunkStuckInProcessing(
            Carbon::now()->subMinutes($threshold_minutes),
            $batch_limit,
            function (Collection $stuck) use ($repository) : void {
                /**
                 * Сдвиг updated_at: повторный передиспатч тех же
                 * уведомлений возможен не раньше следующего порога.
                 */
                $repository->touchAll($stuck->pluck('id')->all());

                /** @var Notification $notification */
                foreach ($stuck as $notification) {
                    SendNotificationJob::dispatch($notification->id);

                    Log::warning('Уведомление зависло в processing, джоба передиспатчена.', [
                        'notification_id' => $notification->id,
                        'attempts' => $notification->attempts_count,
                        'stuck_since' => $notification->updated_at->toIso8601String(),
                    ]);
                }
            }
        );
    }
}
