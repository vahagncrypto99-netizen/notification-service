<?php

declare(strict_types=1);

namespace App\Schedule;

use Illuminate\Console\Scheduling\Schedule as SystemSchedule;

interface DomainScheduleInterface
{
    /**
     * Регистрация задач домена в планировщике.
     */
    public function run(SystemSchedule $schedule) : void;
}
