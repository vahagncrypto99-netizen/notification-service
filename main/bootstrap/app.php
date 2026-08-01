<?php

use App\Http\Middlewares\ApiTokenAuth;
use App\Providers\EventServiceProvider;
use App\Schedule\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))->withRouting(
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)->withProviders([
    EventServiceProvider::class,
])->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        ApiTokenAuth::class,
    ]);
})->withExceptions(function (Exceptions $exceptions) {
    Integration::handles($exceptions);
})->withSchedule(new Schedule)->create();
