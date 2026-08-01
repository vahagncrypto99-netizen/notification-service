<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sentry\State\Scope;

use function Sentry\configureScope;

class ConfigureSentryServiceProvider extends ServiceProvider
{
    /**
     * Глобальные теги Sentry: каждая ошибка несёт версию и окружение —
     * видно, какой деплой её сгенерировал.
     */
    public function register() : void
    {
        configureScope(static function (Scope $scope) : void {
            $scope->setTag('app_version', (string) config('app.version'));
            $scope->setTag('environment', (string) config('app.env'));
        });
    }
}
