<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Support\Facades\Log;

trait QueueSupport
{
    /**
     * Проверка настроек очереди.
     */
    public function checkQueue(string $queue) : string
    {
        $check_queue = $queue;

        /**
         * Проверка названия очереди.
         */
        if (! in_array($check_queue, [
            QueueSettings::HIGH_PRIORITY_QUEUE,
            QueueSettings::LOW_PRIORITY_QUEUE,
        ])) {
            $queue = QueueSettings::LOW_PRIORITY_QUEUE;

            Log::error("Очередь {$check_queue} не определена, установлена {$queue} очередь.");
        }

        return $queue;
    }
}
