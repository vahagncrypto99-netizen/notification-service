<?php

declare(strict_types=1);

namespace App\Base\Notification;

use App\Schedule\DomainScheduleInterface;
use Illuminate\Console\Scheduling\Schedule as SystemSchedule;

class Schedule implements DomainScheduleInterface
{
    /**
     * Регистрация задач домена уведомлений.
     */
    public function run(SystemSchedule $schedule) : void
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
    }
}
