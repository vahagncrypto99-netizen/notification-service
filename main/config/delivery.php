<?php

use App\Services\Delivery\Mail\DefaultSender;

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

];
