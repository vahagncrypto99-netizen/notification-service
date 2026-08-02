<?php

declare(strict_types=1);

namespace App\Base\Notification\Jobs;

use App\Base\Notification\Repository\NotificationRepository;
use App\Queue\Queue;
use App\Queue\QueueSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RedispatchStuckNotificationsJob extends Queue
{
    /**
     * Таймаут выполнения.
     *
     * @var int
     */
    public $timeout = QueueSettings::LOW_PRIORITY_TIMEOUT;

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

        /**
         * Дренаж циклом до опустошения: сдвиг updated_at выводит
         * обработанное из каждой следующей выборки.
         */
        do {
            $stuck = $repository->getStuckInProcessing(
                Carbon::now()->subMinutes($threshold_minutes),
                $batch_limit
            );

            /**
             * Сдвиг updated_at: повторный передиспатч тех же
             * уведомлений возможен не раньше следующего порога.
             */
            $repository->touchAll($stuck->pluck('id')->all());

            foreach ($stuck as $notification) {
                SendNotificationJob::dispatch($notification->id);

                Log::warning('Уведомление зависло в processing, джоба передиспатчена.', [
                    'notification_id' => $notification->id,
                    'attempts' => $notification->attempts_count,
                    'stuck_since' => $notification->updated_at->toIso8601String(),
                ]);
            }
        } while ($stuck->count() === $batch_limit);
    }
}
