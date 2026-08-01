<?php

declare(strict_types=1);

namespace App\Dto;

use Spatie\LaravelData\Data;

class RequestSignatureDto extends Data
{
    /**
     * RequestSignatureDto constructor.
     */
    public function __construct(
        public readonly string $token,
        public readonly string $timestamp,
        public readonly string $signature,
        public readonly string $body,
    ) {
        //
    }
}
