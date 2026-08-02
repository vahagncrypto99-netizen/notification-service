<?php

use App\Base\Notification\Channels\EmailChannelSender;
use App\Base\Notification\Channels\TelegramChannelSender;

return [

    /*
    |--------------------------------------------------------------------------
    | Каналы отправки уведомлений
    |--------------------------------------------------------------------------
    |
    | Карта «канал → класс-отправитель». Новый канал добавляется строкой
    | здесь + case в ChannelEnum + классом, реализующим ChannelSenderInterface.
    | Существующий код при этом не меняется.
    |
    */

    'channels' => [
        'email' => EmailChannelSender::class,
        'telegram' => TelegramChannelSender::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watchdog зависших уведомлений
    |--------------------------------------------------------------------------
    |
    | Уведомления, находящиеся в processing дольше порога, считаются
    | потерянными и передиспатчиваются. Порог должен быть больше
    | максимального суммарного backoff джобы отправки (~4 минуты).
    |
    */

    'watchdog' => [
        'stuck_threshold_minutes' => (int) env('NOTIFICATION_STUCK_THRESHOLD_MINUTES', 10),
        'batch_limit' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Публикация событий наружу
    |--------------------------------------------------------------------------
    |
    | Доменные события (notification.sent / notification.failed) публикуются
    | версионированным конвертом в durable fanout-exchange RabbitMQ —
    | подписчики (аналитика, аудит) заводят свои очереди и биндятся к нему.
    |
    */

    'events' => [
        'enabled' => (bool) env('NOTIFICATION_EVENTS_ENABLED', true),
        'exchange' => env('NOTIFICATION_EVENTS_EXCHANGE', 'notification.events'),
        'version' => 1,
    ],

];
