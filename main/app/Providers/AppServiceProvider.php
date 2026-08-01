<?php

declare(strict_types=1);

namespace App\Providers;

use App\Base\Notification\Channels\ChannelSenderResolver;
use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Reports\ReportFormatterResolver;
use App\Base\Notification\Services\ReportFileStorage;
use App\Http\Responses\ApiResponse;
use App\Services\ApiSignatureValidator;
use App\Services\Delivery\Mail\SenderFactory;
use App\Services\EventPublisher\EventEnvelopeFactory;
use App\Services\EventPublisher\EventPublisherInterface;
use App\Services\EventPublisher\NullEventPublisher;
use App\Services\EventPublisher\RabbitMqEventPublisher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов: composition root приложения — все карты
     * «имя → класс» и конфигурация собираются здесь.
     */
    public function register() : void
    {
        $this->app->singleton(ChannelSenderResolver::class, function (Application $app) {
            return new ChannelSenderResolver($app, (array) config('notification.channels'));
        });

        $this->app->singleton(ReportFormatterResolver::class, function (Application $app) {
            return new ReportFormatterResolver(
                $app,
                (string) config('notification.reports.format'),
                (array) config('notification.reports.formatters'),
            );
        });

        $this->app->singleton(SenderFactory::class, function (Application $app) {
            return new SenderFactory($app, (array) config('delivery.mail'));
        });

        $this->app->bind(ReportFileStorage::class, function (Application $app) {
            return new ReportFileStorage(
                $app->make(ReportFormatterResolver::class),
                Storage::disk((string) config('notification.reports.disk')),
                (string) config('notification.reports.directory'),
            );
        });

        $this->app->bind(EventEnvelopeFactory::class, function () {
            return new EventEnvelopeFactory(
                config('app.name').'.'.config('app.env'),
                (int) config('notification.events.version'),
            );
        });

        /**
         * Строго singleton: подписчик резолвит publisher на каждое
         * событие, и новый экземпляр плодил бы AMQP-каналы до channel_max.
         */
        $this->app->singleton(EventPublisherInterface::class, function (Application $app) {
            if (! config('notification.events.enabled')) {
                return new NullEventPublisher;
            }

            return new RabbitMqEventPublisher(
                $app->make('queue'),
                (string) config('notification.events.exchange'),
            );
        });

        $this->app->singleton(ApiSignatureValidator::class, function (Application $app) {
            return new ApiSignatureValidator(
                $this->parseApiClients((string) config('auth.api.clients')),
                (int) config('auth.api.signature_ttl'),
                $this->validatedSignatureAlgo((string) config('auth.api.signature_algo')),
                $app->make('cache.store'),
            );
        });
    }

    /**
     * Загрузка сервисов: fail-fast валидация конфигурации и rate limiter.
     *
     * @throws RuntimeException при некорректной конфигурации
     */
    public function boot() : void
    {
        /**
         * Ранняя валидация конфигурации аутентификации: кривой
         * API_AUTH_CLIENTS роняет приложение на старте с понятной
         * ошибкой, а не молчаливыми 401 на каждом запросе.
         */
        $this->app->make(ApiSignatureValidator::class);

        /**
         * Каждый канал из ChannelEnum обязан иметь отправителя в конфиге —
         * рассинхрон enum и карты каналов виден на старте, а не на первой
         * отправке.
         */
        foreach (ChannelEnum::cases() as $channel) {
            if (! isset(config('notification.channels')[$channel->value])) {
                throw new RuntimeException(
                    "Канал '{$channel->value}' не имеет отправителя в notification.channels."
                );
            }
        }

        /**
         * Лимит запросов на сервис-клиент (bulkhead): взбесившийся клиент
         * упирается в свой лимит, не задевая остальных. До аутентификации
         * токену доверять нельзя: неизвестный или отсутствующий Bearer
         * считается по IP — ротация случайных токенов не создаёт свежие
         * bucket-ы и не обходит лимит. В ключ попадает хеш токена,
         * а не сам токен.
         */
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken();

            $known = $token !== null
                && $this->app->make(ApiSignatureValidator::class)->knownToken($token);

            $key = $known ? hash('sha256', (string) $token) : (string) $request->ip();

            return Limit::perMinute((int) config('auth.api.rate_limit'))->by($key)->response(
                fn () => ApiResponse::error('Слишком много запросов.', 429)
            );
        });
    }

    /**
     * Минимальная длина секрета подписи.
     */
    private const MIN_SECRET_LENGTH = 12;

    /**
     * Разбор пар «токен:секрет_подписи» из конфигурации.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException при некорректной или пустой конфигурации
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

            if (isset($clients[$token])) {
                throw new RuntimeException("Дубликат токена в API_AUTH_CLIENTS: '{$token}'.");
            }

            if (strlen($secret) < self::MIN_SECRET_LENGTH) {
                throw new RuntimeException(
                    "Секрет токена '{$token}' короче ".self::MIN_SECRET_LENGTH.' символов.'
                );
            }

            $clients[$token] = $secret;
        }

        /**
         * Пустая карта — все запросы молча получали бы 401;
         * ошибка конфигурации должна быть видна на старте.
         */
        if ($clients === []) {
            throw new RuntimeException('API_AUTH_CLIENTS пуст — не настроен ни один клиент API.');
        }

        return $clients;
    }

    /**
     * Проверка, что настроенный HMAC-алгоритм поддерживается.
     *
     * @throws RuntimeException для неподдерживаемого алгоритма
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
