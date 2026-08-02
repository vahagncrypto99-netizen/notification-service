<?php

declare(strict_types=1);

namespace App\Domains\Notification;

use App\Domains\Notification\Jobs\RedispatchStuckNotificationsJob;
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
        $schedule->job(new RedispatchStuckNotificationsJob)->everyFiveMinutes();
    }
}
