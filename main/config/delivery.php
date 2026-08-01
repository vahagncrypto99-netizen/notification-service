<?php

use App\Services\Delivery\Mail\DefaultSender;
use App\Services\Delivery\Messenger\MessageFormatter;
use App\Services\Delivery\Messenger\Telegram\TelegramClient;

return [

    /*
    |--------------------------------------------------------------------------
    | Почтовый канал
    |--------------------------------------------------------------------------
    |
    | Конфигурация подсистемы доставки (app/Services/Delivery).
    | Именованные сендеры: реализация выбирается фабрикой SenderFactory::mail()
    | по имени из карты senders. Новый провайдер (Unisender, SES, ...) —
    | новый класс-наследник Sender + строка в карте.
    |
    */

    'mail' => [

        'default_sender' => env('NOTIFICATIONS_MAIL_SENDER', 'default'),

        'senders' => [
            'default' => [
                'handler' => DefaultSender::class,
            ],
        ],

        /*
         * Разрешённые адреса отправителя. Письмо с чужим from уходит
         * от дефолтного адреса, оригинал — в Reply-To.
         */
        'from' => [
            'default' => [
                'email' => env('NOTIFICATIONS_MAIL_FROM', 'noreply@notification-service.local'),
                'name' => env('NOTIFICATIONS_MAIL_FROM_NAME', 'Notification Service'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Мессенджеры
    |--------------------------------------------------------------------------
    |
    | Карта «мессенджер → реализация»: sender (клиент API) и форматтер
    | сообщений. Новый мессенджер добавляется строкой в карту.
    |
    */

    'messengers' => [
        'telegram' => [
            'sender' => TelegramClient::class,
            'message_formatter' => MessageFormatter::class,
        ],
    ],

];
