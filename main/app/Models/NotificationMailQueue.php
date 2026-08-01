<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $from_email
 * @property string|null $from_name
 * @property string $to_email
 * @property string $subject
 * @property string $message
 * @property array<string, mixed>|null $additionally
 * @property string|null $sender
 * @property int $priority
 * @property string|null $send_at
 */
class NotificationMailQueue extends Model
{
    protected $table = 'notification_mail_queue';

    protected $casts = [
        'additionally' => 'array',
        'priority' => 'integer',
    ];
}
