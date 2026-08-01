<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Dto\ChannelMessageDto;
use App\Services\Delivery\Messenger\Dto\SenderDto;
use App\Services\Delivery\Messenger\MessageFormatter;
use App\Services\Delivery\Messenger\Telegram\TelegramClient;
use App\Services\Delivery\PermanentDeliveryException;
use RuntimeException;

class TelegramChannelSender implements ChannelSenderInterface
{
    /**
     * TelegramChannelSender constructor.
     */
    public function __construct(
        private readonly TelegramClient $client,
        private readonly MessageFormatter $message_formatter,
    ) {
        //
    }

    /**
     * Отправка через клиент Bot API с троттлингом под лимит Telegram.
     * Chat ID в реальной системе брался бы из привязки аккаунта.
     *
     * @throws PermanentDeliveryException при неисправимом отказе получателя
     * @throws RuntimeException при транзиентном сбое (уходит в ретрай)
     */
    public function send(ChannelMessageDto $message) : void
    {
        $response = $this->client->send(SenderDto::from([
            'chat_id' => "user-{$message->user_id}",
            'message' => $this->message_formatter->prepareMessage($message->message),
        ]));

        if ($response->success) {
            return;
        }

        if ($response->should_unsubscribe) {
            /**
             * Получатель заблокировал бота — ретраи бессмысленны
             * (в боевой реализации здесь отписка пользователя).
             */
            throw new PermanentDeliveryException("Получатель user-{$message->user_id} недоступен.");
        }

        if ($response->should_retry) {
            throw new RuntimeException((string) $response->message);
        }

        /**
         * Ошибка без флага повтора — неисправимый отказ канала.
         */
        throw new PermanentDeliveryException((string) $response->message);
    }
}
