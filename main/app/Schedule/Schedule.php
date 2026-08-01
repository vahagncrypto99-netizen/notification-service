<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Services\Delivery\Mail\Schedule as MailSchedule;
use App\Services\Delivery\Messenger\MessengerResolver;
use Illuminate\Console\Scheduling\Schedule as SystemSchedule;

class Schedule
{
    /**
     * Регистрация задач планировщика.
     */
    public function __invoke(SystemSchedule $schedule) : void
    {
        /**
         * Watchdog гарантии доставки: передиспатч уведомлений,
         * зависших в processing (потерянные джобы).
         */
        $schedule->command('notification:redispatch-stuck')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        /**
         * Watchdog отчётов: передиспатч зависших pending (потерянный
         * dispatch) и processing (убитый воркер).
         */
        $schedule->command('notification:redispatch-stuck-reports')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        /**
         * Отправка писем из очереди почтового канала.
         */
        $schedule->call(function () {
            app(MailSchedule::class)->send();
        })->name('notifications_mail_queue')->everyMinute()->withoutOverlapping();

        /**
         * Отправка сообщений из очередей мессенджеров.
         */
        $schedule->call(function () {
            $resolver = app(MessengerResolver::class);

            foreach ($resolver->available() as $messenger) {
                $resolver->makeSchedule($messenger)->send();
            }
        })->name('notifications_messenger_queue')->everyMinute()->withoutOverlapping();
    }
}
