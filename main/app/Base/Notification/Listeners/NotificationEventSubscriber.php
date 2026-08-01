<?php

declare(strict_types=1);

namespace App\Base\Notification\Listeners;

use App\Base\Notification\Events\NotificationFailed;
use App\Base\Notification\Events\NotificationSent;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class NotificationEventSubscriber
{
    /**
     * Уведомление успешно отправлено.
     */
    public function handleSent(NotificationSent $event) : void
    {
        $notification = $event->notification;

        Log::info(
            "Уведомление #{$notification->id} отправлено пользователю {$notification->user_id} через {$notification->channel->value}.",
            ['attempts' => $notification->attempts_count]
        );
    }

    /**
     * Доставка уведомления завершилась ошибкой (попытки исчерпаны).
     */
    public function handleFailed(NotificationFailed $event) : void
    {
        $notification = $event->notification;

        Log::error(
            "Уведомление #{$notification->id} не доставлено пользователю {$notification->user_id} через {$notification->channel->value}.",
            [
                'attempts' => $notification->attempts_count,
                'error' => $notification->last_error,
            ]
        );
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
