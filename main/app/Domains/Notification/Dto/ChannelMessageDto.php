<?php

declare(strict_types=1);

namespace App\Domains\Notification\Dto;

use Spatie\LaravelData\Data;

class ChannelMessageDto extends Data
{
    /**
     * ChannelMessageDto constructor.
     */
    public function __construct(
        public readonly int $notification_id,
        public readonly int $user_id,
        public readonly string $message,
    ) {
        //
    }
}
