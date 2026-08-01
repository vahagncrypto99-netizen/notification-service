<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Base\Notification\Jobs\SendNotificationJob;
use App\Base\Notification\Repository\NotificationRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RedispatchStuckNotifications extends Command
{
    /**
     * Сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'notification:redispatch-stuck';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Передиспатч уведомлений, зависших в статусе processing';

    /**
     * Выполнение команды.
     */
    public function handle(NotificationRepository $repository) : int
    {
        $threshold_minutes = (int) config('notification.watchdog.stuck_threshold_minutes');
        $batch_limit = (int) config('notification.watchdog.batch_limit');

        $total = 0;

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

            $total += $stuck->count();
        } while ($stuck->count() === $batch_limit);

        $this->info($total > 0 ? "Передиспатчено уведомлений: {$total}." : 'Зависших уведомлений нет.');

        return self::SUCCESS;
    }
}
