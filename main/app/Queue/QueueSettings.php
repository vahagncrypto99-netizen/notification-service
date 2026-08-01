<?php

declare(strict_types=1);

namespace App\Queue;

class QueueSettings
{
    /**
     * Очередь высокого приоритета (доставка уведомлений).
     */
    const HIGH_PRIORITY_QUEUE = 'high_priority_queue';

    /**
     * Таймаут джобы высокоприоритетной очереди, секунды.
     */
    const HIGH_PRIORITY_TIMEOUT = 60;

    /**
     * Очередь низкого приоритета (генерация отчётов).
     */
    const LOW_PRIORITY_QUEUE = 'low_priority_queue';

    /**
     * Таймаут джобы низкоприоритетной очереди, секунды.
     */
    const LOW_PRIORITY_TIMEOUT = 60 * 10;
}
