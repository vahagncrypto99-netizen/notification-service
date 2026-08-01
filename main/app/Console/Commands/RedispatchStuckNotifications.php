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
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:redispatch-stuck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Передиспатч уведомлений, зависших в статусе processing';

    /**
     * Execute the console command.
     */
    public function handle(NotificationRepository $repository) : int
    {
        $threshold_minutes = (int) config('notification.watchdog.stuck_threshold_minutes');
        $batch_limit = (int) config('notification.watchdog.batch_limit');

        $total = 0;

        /**
         * Дренаж циклом до опустошения: touch() сдвигает updated_at,
         * поэтому каждая следующая выборка отдаёт только необработанное,
         * а бэклог после долгого простоя разгребается за один запуск.
         */
        do {
            $stuck = $repository->getStuckInProcessing(
                Carbon::now()->subMinutes($threshold_minutes),
                $batch_limit
            );

            foreach ($stuck as $notification) {
                /**
                 * touch() сдвигает updated_at — повторный передиспатч того же
                 * уведомления возможен не раньше следующего порога.
                 */
                $notification->touch();

                SendNotificationJob::dispatch($notification->id);

                Log::warning(
                    "Уведомление #{$notification->id} зависло в processing, джоба передиспатчена.",
                    ['attempts' => $notification->attempts_count]
                );
            }

            $total += $stuck->count();
        } while ($stuck->count() === $batch_limit);

        $this->info($total > 0 ? "Передиспатчено уведомлений: {$total}." : 'Зависших уведомлений нет.');

        return self::SUCCESS;
    }
}
