<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;
use App\Services\Delivery\Messenger\Messenger;
use App\Services\Delivery\Messenger\MessengerService;
use RuntimeException;

class TelegramChannelSender implements ChannelSenderInterface
{
    /**
     * TelegramChannelSender constructor.
     */
    public function __construct(
        private readonly MessengerService $messenger_service,
    ) {
        //
    }

    /**
     * Передача уведомления в очередь Telegram: отправку выполняет крон
     * через клиент Bot API с троттлингом под лимит Telegram. Chat ID
     * в реальной системе брался бы из привязки аккаунта пользователя.
     */
    public function send(ChannelMessageDto $message) : void
    {
        if (config('notification.simulate_failures')) {
            throw new RuntimeException('Симулированный сбой отправки в Telegram.');
        }

        $this->messenger_service->enqueue(
            Messenger::TELEGRAM,
            "user-{$message->user_id}",
            $message->message,
            $message->notification_id,
        );
    }
}
