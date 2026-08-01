<?php

declare(strict_types=1);

namespace App\Schedule;

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
    }
}
