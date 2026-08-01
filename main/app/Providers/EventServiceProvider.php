<?php

declare(strict_types=1);

namespace App\Providers;

use App\Base\Notification\Listeners\NotificationEventSubscriber;

class EventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        //
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        NotificationEventSubscriber::class,
    ];
}
