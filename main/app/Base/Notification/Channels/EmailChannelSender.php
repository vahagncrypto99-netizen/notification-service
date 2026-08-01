<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;
use App\Services\Notifications\Channels\Mail\Dto\SenderDto;
use App\Services\Notifications\Notification;
use RuntimeException;

class EmailChannelSender implements ChannelSenderInterface
{
    /**
     * EmailChannelSender constructor.
     */
    public function __construct(
        private readonly Notification $notifications,
    ) {
        //
    }

    /**
     * Передача уведомления почтовому каналу: письмо уходит в очередь
     * канала (приоритет/отложка) и отправляется кроном через сендер
     * из конфигурации. Email получателя в реальной системе брался бы
     * из профиля пользователя.
     */
    public function send(ChannelMessageDto $message) : void
    {
        if (config('notification.simulate_failures')) {
            throw new RuntimeException('Симулированный сбой отправки email.');
        }

        $this->notifications->mail()->send(SenderDto::from([
            'from_email' => null,
            'from_name' => null,
            'to_email' => "user-{$message->user_id}@example.stub",
            'subject' => "Уведомление #{$message->notification_id}",
            'message' => $message->message,
            'queue' => true,
        ]));
    }
}
