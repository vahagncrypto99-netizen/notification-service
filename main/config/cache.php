<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Кэш
    |--------------------------------------------------------------------------
    |
    | Redis — кэш и счётчики rate limiting (разделяемое хранилище,
    | переживает масштабирование инстансов); array — для тестов.
    |
    */

    'default' => env('CACHE_STORE', 'redis'),

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

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'notification_service_cache_'),

];
