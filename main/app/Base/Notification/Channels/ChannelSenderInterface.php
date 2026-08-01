<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;
use App\Services\Delivery\PermanentDeliveryException;

interface ChannelSenderInterface
{
    /**
     * Отправка сообщения в канал.
     *
     * @throws PermanentDeliveryException при неисправимом отказе
     * @throws \Throwable при транзиентном сбое (уходит в ретрай)
     */
    public function send(ChannelMessageDto $message) : void;
}
