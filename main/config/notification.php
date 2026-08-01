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
    | Симуляция сбоя отправки
    |--------------------------------------------------------------------------
    |
    | Для тестов и ручной проверки ветки ошибок: при true заглушки каналов
    | бросают исключение вместо успешной «отправки».
    |
    */

    'simulate_failures' => env('NOTIFICATION_SIMULATE_FAILURES', false),

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
