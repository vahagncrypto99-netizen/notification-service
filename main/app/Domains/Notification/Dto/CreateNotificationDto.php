<?php

declare(strict_types=1);

namespace App\Domains\Notification\Dto;

use App\Domains\Notification\Enum\ChannelEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class CreateNotificationDto extends Data
{
    /**
     * CreateNotificationDto constructor.
     */
    public function __construct(
        public readonly string $message,
        public readonly int $user_id,
        public readonly ChannelEnum $channel,
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
            'message' => ['required', 'string', 'max:500'],
            'user_id' => ['required', 'integer', 'min:1'],
            'channel' => ['required', Rule::enum(ChannelEnum::class)],
        ];
    }
}
