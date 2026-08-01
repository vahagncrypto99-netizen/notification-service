<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class Queue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, QueueSupport, SerializesModels;
    use Queueable {
        onQueue as onQueueBase;
    }

    /**
     * Количество попыток выполнения джобы.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Назначение очереди с проверкой имени: неизвестное имя
     * откатывается на низкоприоритетную очередь.
     *
     * @param  string  $queue
     * @return $this
     */
    public function onQueue($queue)
    {
        $queue = $this->checkQueue($queue);

        return $this->onQueueBase($queue);
    }
}
