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
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Set the desired queue for the job.
     *
     * @param  string|null  $queue
     * @return $this
     */
    public function onQueue($queue)
    {
        $queue = $this->checkQueue($queue);

        return $this->onQueueBase($queue);
    }
}
