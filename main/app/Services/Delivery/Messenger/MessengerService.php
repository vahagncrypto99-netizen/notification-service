<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Services\Delivery\Messenger\Repository\MessengerQueueRepository;

class MessengerService
{
    /**
     * MessengerService constructor.
     */
    public function __construct(
        protected MessengerQueueRepository $queue,
    ) {
        //
    }

    /**
     * Постановка сообщения в очередь мессенджера.
     *
     * Идемпотентность повторной постановки: на одно уведомление —
     * не больше одной записи в очереди (передиспатч watchdog-ом
     * не приводит к дублю сообщения).
     */
    public function enqueue(
        string $messenger,
        string $messenger_id,
        string $message,
        ?int $notification_id = null,
        ?string $send_at = null,
    ) : void {
        if ($notification_id !== null && $this->queue->existsForNotification($notification_id)) {
            return;
        }

        $this->queue->addMessage($messenger, $messenger_id, $message, $send_at, $notification_id);
    }
}
