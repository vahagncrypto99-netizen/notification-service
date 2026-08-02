<?php

declare(strict_types=1);

namespace App\Domains\Notification\Events;

use App\Models\Notification;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationFailed
{
    use Dispatchable;

    /**
     * NotificationFailed constructor.
     */
    public function __construct(
        public readonly Notification $notification
    ) {
        //
    }
}
