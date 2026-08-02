<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Auth\ApiClientsParser;
use App\Domains\Auth\ApiSignatureValidator;
use App\Domains\Notification\Channels\ChannelSenderResolver;
use App\Domains\Notification\Enum\ChannelEnum;
use App\Domains\Notification\Schedule as NotificationSchedule;
use App\Domains\Report\Formatters\ReportFormatterResolver;
use App\Domains\Report\Schedule as ReportSchedule;
use App\Domains\Report\Services\ReportFileStorage;
use App\Http\Responses\ApiResponse;
use App\Schedule\Schedule;
use App\Services\Delivery\Mail\SenderFactory;
use App\Services\EventPublisher\EventEnvelopeFactory;
use App\Services\EventPublisher\EventPublisherInterface;
use App\Services\EventPublisher\NullEventPublisher;
use App\Services\EventPublisher\RabbitMqEventPublisher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Container\BindingResolutionException;
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
        /**
         * Планировщик собирается из шедулеров доменов: новый домен
         * добавляет свой класс-шедулер строкой в этот список.
         */
        $this->app->singleton(Schedule::class, function (Application $app) {
            return new Schedule([
                $app->make(NotificationSchedule::class),
                $app->make(ReportSchedule::class),
            ]);
        });

        $this->app->singleton(ChannelSenderResolver::class, function (Application $app) {
            return new ChannelSenderResolver($app, (array) config('notification.channels'));
        });

        $this->app->singleton(ReportFormatterResolver::class, function (Application $app) {
            return new ReportFormatterResolver(
                $app,
                (string) config('report.format'),
                (array) config('report.formatters'),
            );
        });

        $this->app->singleton(SenderFactory::class, function (Application $app) {
            return new SenderFactory($app, (array) config('delivery.mail'));
        });

        $this->app->bind(ReportFileStorage::class, function (Application $app) {
            return new ReportFileStorage(
                $app->make(ReportFormatterResolver::class),
                Storage::disk((string) config('report.disk')),
                (string) config('report.directory'),
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
            $parser = new ApiClientsParser;

            return new ApiSignatureValidator(
                $parser->parse((string) config('auth.api.clients')),
                (int) config('auth.api.signature_ttl'),
                $parser->validatedAlgo((string) config('auth.api.signature_algo')),
                $app->make('cache.store'),
            );
        });
    }

    /**
     * Загрузка сервисов: fail-fast валидация конфигурации и rate limiter.
     *
     * @throws RuntimeException|BindingResolutionException при некорректной конфигурации
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
}
