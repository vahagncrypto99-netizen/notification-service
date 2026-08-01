<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use Carbon\Carbon;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $message
 * @property NotificationStatusEnum $status
 * @property int $user_id
 * @property ChannelEnum $channel
 * @property int $attempts_count
 * @property Carbon|null $last_attempted_at
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /**
     * Таблица модели.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * Приведение типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => NotificationStatusEnum::class,
        'channel' => ChannelEnum::class,
        'user_id' => 'integer',
        'attempts_count' => 'integer',
        'last_attempted_at' => 'datetime',
    ];

    /**
     * Зафиксировать попытку отправки (атомарный инкремент).
     */
    public function registerAttempt() : void
    {
        $this->increment('attempts_count', 1, ['last_attempted_at' => Carbon::now()]);
    }

    /**
     * Находится ли уведомление в указанном статусе.
     */
    public function inStatus(NotificationStatusEnum $status) : bool
    {
        return $this->status === $status;
    }
}
