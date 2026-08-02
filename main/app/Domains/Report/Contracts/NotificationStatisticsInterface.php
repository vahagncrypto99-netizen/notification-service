<?php

declare(strict_types=1);

namespace App\Domains\Report\Contracts;

use Carbon\Carbon;

interface NotificationStatisticsInterface
{
    /**
     * Агрегация уведомлений пользователя за период: по каждому каналу —
     * всего уведомлений и из них с ошибкой.
     *
     * @return array<int, array{channel: string, total: int, failed: int}>
     */
    public function aggregateByChannel(int $user_id, Carbon $period_from, Carbon $period_to) : array;
}
