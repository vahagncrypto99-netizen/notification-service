<?php

use App\Providers\AppServiceProvider;
use App\Providers\ConfigureSentryServiceProvider;
use App\Providers\EventServiceProvider;

return [
    AppServiceProvider::class,
    ConfigureSentryServiceProvider::class,
    EventServiceProvider::class,
];
