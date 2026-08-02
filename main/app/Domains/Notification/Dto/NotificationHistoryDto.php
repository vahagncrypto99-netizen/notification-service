<?php

declare(strict_types=1);

namespace App\Domains\Notification\Dto;

use App\Domains\Notification\Enum\ChannelEnum;
use App\Domains\Notification\Enum\NotificationStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class NotificationHistoryDto extends Data
{
    /**
     * NotificationHistoryDto constructor.
     */
    public function __construct(
        public readonly int $user_id,
        public readonly ?NotificationStatusEnum $status = null,
        public readonly ?ChannelEnum $channel = null,
    ) {
        //
    }

    /**
     * Правила валидации входящих данных.
     *
     * @return array<string, mixed>
     */
    public static function rules() : array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(NotificationStatusEnum::class)],
            'channel' => ['nullable', Rule::enum(ChannelEnum::class)],
        ];
    }
}
