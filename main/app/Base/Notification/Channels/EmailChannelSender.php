<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmailChannelSender implements ChannelSenderInterface
{
    /**
     * Имитация отправки в email: реальной интеграции нет — только лог.
     */
    public function send(ChannelMessageDto $message) : void
    {
        if (config('notification.simulate_failures')) {
            throw new RuntimeException(
                'Симулированный сбой отправки email.'
            );
        }

        Log::info(
            "Email-уведомление #{$message->notification_id} отправлено пользователю {$message->user_id}.",
            ['message' => $message->message]
        );
    }
}
