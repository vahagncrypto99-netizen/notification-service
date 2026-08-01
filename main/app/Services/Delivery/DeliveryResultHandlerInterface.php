<?php

declare(strict_types=1);

namespace App\Services\Delivery;

interface DeliveryResultHandlerInterface
{
    /**
     * Сообщение фактически отправлено каналом.
     */
    public function sent(int $notification_id) : void;

    /**
     * Канал отказался от отправки окончательно.
     */
    public function failed(int $notification_id, string $error) : void;
}
