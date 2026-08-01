<?php

use App\Base\Notification\Channels\EmailChannelSender;
use App\Base\Notification\Channels\TelegramChannelSender;
use App\Base\Notification\Reports\CsvReportFormatter;

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

        /*
         * У отчётов порог больше: максимальный зазор между bump-ами
         * updated_at живой генерации = таймаут джобы (10 мин) + backoff
         * (2 мин) — порог обязан его превышать.
         */
        'report_stuck_threshold_minutes' => (int) env('NOTIFICATION_REPORT_STUCK_THRESHOLD_MINUTES', 15),
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

    /*
    |--------------------------------------------------------------------------
    | Отчёты по уведомлениям
    |--------------------------------------------------------------------------
    |
    | format — активный тип отчёта; formatters — карта «тип → стратегия».
    | Новый формат добавляется классом, реализующим ReportFormatterInterface,
    | и строкой в карте. Существующий код при этом не меняется.
    |
    */

    'reports' => [
        'disk' => env('NOTIFICATION_REPORTS_DISK', 'local'),
        'directory' => 'reports',
        'format' => env('NOTIFICATION_REPORT_FORMAT', 'csv'),
        'formatters' => [
            'csv' => CsvReportFormatter::class,
        ],
    ],

];
