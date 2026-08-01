<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail\Dto;

use Spatie\LaravelData\Data;

class FromAddressDto extends Data
{
    /**
     * FromAddressDto constructor.
     */
    public function __construct(
        public readonly string $from_email,
        public readonly string $from_name,
        public readonly ?string $reply_to,
    ) {
        //
    }
}
