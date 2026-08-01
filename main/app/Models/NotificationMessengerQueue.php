<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $notification_id
 * @property string $messenger
 * @property string $messenger_id
 * @property string $message
 * @property int $attempts
 * @property string|null $send_at
 */
class NotificationMessengerQueue extends Model
{
    protected $table = 'notification_messenger_queue';

    protected $casts = [
        'attempts' => 'integer',
    ];
}
