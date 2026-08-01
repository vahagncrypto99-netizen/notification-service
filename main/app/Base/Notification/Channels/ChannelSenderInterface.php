<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;

interface ChannelSenderInterface
{
    /**
     * Отправка сообщения в канал.
     *
     * @throws \Throwable при сбое отправки
     */
    public function send(ChannelMessageDto $message) : void;
}
