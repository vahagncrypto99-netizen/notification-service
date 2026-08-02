<?php

use App\Base\Report\Formatters\CsvReportFormatter;

return [

    /*
    |--------------------------------------------------------------------------
    | Хранение и формат отчётов
    |--------------------------------------------------------------------------
    |
    | format — активный тип отчёта; formatters — карта «тип → стратегия».
    | Новый формат добавляется классом, реализующим ReportFormatterInterface,
    | и строкой в карте. Существующий код при этом не меняется.
    |
    */

    'disk' => env('NOTIFICATION_REPORTS_DISK', 'local'),
    'directory' => 'reports',
    'format' => env('NOTIFICATION_REPORT_FORMAT', 'csv'),
    'formatters' => [
        'csv' => CsvReportFormatter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watchdog зависших отчётов
    |--------------------------------------------------------------------------
    |
    | Порог должен превышать максимальный зазор между bump-ами updated_at
    | живой генерации: таймаут джобы (10 мин) + backoff (2 мин).
    |
    */

    'watchdog' => [
        'stuck_threshold_minutes' => (int) env('NOTIFICATION_REPORT_STUCK_THRESHOLD_MINUTES', 15),
        'batch_limit' => 100,
    ],

];
