<?php

declare(strict_types=1);

namespace App\Base\Notification\Listeners;

use App\Base\Notification\Events\NotificationFailed;
use App\Base\Notification\Events\NotificationSent;
use App\Models\Notification;
use App\Services\EventPublisher\EventEnvelopeFactory;
use App\Services\EventPublisher\EventPublisherInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationEventSubscriber
{
    private const EVENT_SENT = 'notification.sent';

    private const EVENT_FAILED = 'notification.failed';

    /**
     * NotificationEventSubscriber constructor.
     */
    public function __construct(
        private readonly EventPublisherInterface $event_publisher,
        private readonly EventEnvelopeFactory $envelope_factory,
    ) {
        //
    }

    /**
     * Уведомление успешно отправлено.
     *
     *
     * @throws Throwable
     */
    public function handleSent(NotificationSent $event) : void
    {
        $notification = $event->notification;

        Log::info(
            "Уведомление #{$notification->id} отправлено пользователю "
                ."{$notification->user_id} через {$notification->channel->value}.",
            ['attempts' => $notification->attempts_count]
        );

        $this->publishEvent(self::EVENT_SENT, $notification);
    }

    /**
     * Доставка уведомления завершилась ошибкой (попытки исчерпаны).
     *
     *
     * @throws Throwable
     */
    public function handleFailed(NotificationFailed $event) : void
    {
        $notification = $event->notification;

        Log::error(
            "Уведомление #{$notification->id} не доставлено пользователю "
                ."{$notification->user_id} через {$notification->channel->value}.",
            [
                'attempts' => $notification->attempts_count,
                'error' => $notification->last_error,
            ]
        );

        $this->publishEvent(self::EVENT_FAILED, $notification);
    }

    /**
     * Публикация события наружу.
     *
     *
     * @throws Throwable
     */
    private function publishEvent(string $type, Notification $notification) : void
    {
        try {
            $this->event_publisher->publish($this->envelope_factory->make($type, [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'channel' => $notification->channel->value,
                'status' => $notification->status->value,
                'attempts' => $notification->attempts_count,
                'error' => $notification->last_error,
            ]));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Карта «событие → обработчик» подписчика.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events) : array
    {
        return [
            NotificationSent::class => 'handleSent',
            NotificationFailed::class => 'handleFailed',
        ];
    }
}
