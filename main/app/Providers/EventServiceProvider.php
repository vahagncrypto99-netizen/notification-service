<?php

declare(strict_types=1);

namespace App\Providers;

use App\Base\Notification\Listeners\NotificationEventSubscriber;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    /**
     * Карта «событие → слушатели».
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        //
    ];

    /**
     * Регистрируемые подписчики событий.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        NotificationEventSubscriber::class,
    ];
}
