<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Кэш
    |--------------------------------------------------------------------------
    |
    | Сервису кэш почти не нужен: file — для локальной работы,
    | array — для тестов. Отдельная кэш-инфраструктура не держится.
    |
    */

    'default' => env('CACHE_STORE', 'file'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'notification_service_cache_'),

];
