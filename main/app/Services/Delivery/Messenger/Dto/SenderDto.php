<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger\Dto;

use Spatie\LaravelData\Data;

class SenderDto extends Data
{
    /**
     * SenderDto constructor.
     */
    public function __construct(
        public readonly int|string $chat_id,
        public readonly string $message,
    ) {
        //
    }
}
