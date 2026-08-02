<?php

declare(strict_types=1);

namespace App\Schedule;

use Illuminate\Console\Scheduling\Schedule as SystemSchedule;

class Schedule
{
    /**
     * Schedule constructor.
     *
     * @param  array<int, DomainScheduleInterface>  $schedules  шедулеры доменов
     */
    public function __construct(
        protected array $schedules = [],
    ) {
        //
    }

    /**
     * Регистрация задач всех доменов.
     */
    public function __invoke(SystemSchedule $schedule) : void
    {
        foreach ($this->schedules as $domain_schedule) {
            $domain_schedule->run($schedule);
        }
    }
}
