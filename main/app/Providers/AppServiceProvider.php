<?php

declare(strict_types=1);

namespace App\Providers;

use App\Base\Notification\Channels\ChannelSenderResolver;
use App\Base\Notification\Reports\ReportFormatterResolver;
use App\Base\Notification\Services\ReportFileStorage;
use App\Services\ApiSignatureValidator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register() : void
    {
        $this->app->singleton(ChannelSenderResolver::class);
        $this->app->singleton(ReportFormatterResolver::class);

        $this->app->bind(ReportFileStorage::class, function (Application $app) {
            return new ReportFileStorage(
                $app->make(ReportFormatterResolver::class),
                Storage::disk((string) config('notification.reports.disk'))
            );
        });

        $this->app->bind(ApiSignatureValidator::class, function () {
            return new ApiSignatureValidator(
                $this->parseApiClients((string) config('auth.api.clients')),
                (int) config('auth.api.signature_ttl'),
                $this->validatedSignatureAlgo((string) config('auth.api.signature_algo')),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot() : void
    {
        /**
         * Ранняя валидация конфигурации аутентификации: кривой
         * API_AUTH_CLIENTS роняет приложение на старте с понятной
         * ошибкой, а не молчаливыми 401 на каждом запросе.
         */
        $this->app->make(ApiSignatureValidator::class);
    }

    /**
     * Разбор пар «токен:секрет_подписи» из конфигурации.
     *
     * @return array<string, string>
     */
    private function parseApiClients(string $raw) : array
    {
        $clients = [];

        foreach (array_filter(array_map('trim', explode(',', $raw))) as $pair) {
            $token = strstr($pair, ':', true);
            $secret = (string) substr((string) strstr($pair, ':'), 1);

            if ($token === false || $token === '' || $secret === '') {
                throw new RuntimeException(
                    "Некорректная пара в API_AUTH_CLIENTS: '{$pair}' — ожидается формат token:secret."
                );
            }

            $clients[$token] = $secret;
        }

        return $clients;
    }

    /**
     * Проверка, что настроенный HMAC-алгоритм поддерживается.
     */
    private function validatedSignatureAlgo(string $algo) : string
    {
        if (! in_array($algo, hash_hmac_algos(), true)) {
            throw new RuntimeException(
                "Неподдерживаемый алгоритм подписи в API_SIGNATURE_ALGO: '{$algo}'."
            );
        }

        return $algo;
    }
}
