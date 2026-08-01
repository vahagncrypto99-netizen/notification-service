<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramChannelSender implements ChannelSenderInterface
{
    /**
     * Имитация отправки в Telegram: реальной интеграции нет — только лог.
     */
    public function send(ChannelMessageDto $message) : void
    {
        if (config('notification.simulate_failures')) {
            throw new RuntimeException(
                'Симулированный сбой отправки в Telegram.'
            );
        }

        Log::info(
            "Telegram-уведомление #{$message->notification_id} отправлено пользователю {$message->user_id}.",
            ['message' => $message->message]
        );
    }
}
