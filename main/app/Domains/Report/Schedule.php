<?php

declare(strict_types=1);

namespace App\Domains\Report;

use App\Domains\Report\Jobs\RedispatchStuckReportsJob;
use App\Schedule\DomainScheduleInterface;
use Illuminate\Console\Scheduling\Schedule as SystemSchedule;

class Schedule implements DomainScheduleInterface
{
    /**
     * Регистрация задач домена отчётов.
     */
    public function run(SystemSchedule $schedule) : void
    {
        /**
         * Watchdog отчётов: передиспатч зависших pending (потерянный
         * dispatch) и processing (убитый воркер).
         */
        $schedule->job(new RedispatchStuckReportsJob)->everyFiveMinutes();
    }
}
