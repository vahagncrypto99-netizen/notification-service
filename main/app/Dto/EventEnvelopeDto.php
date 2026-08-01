<?php

declare(strict_types=1);

namespace App\Dto;

use Spatie\LaravelData\Data;

class EventEnvelopeDto extends Data
{
    /**
     * EventEnvelopeDto constructor.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly string $publisher,
        public readonly int $version,
        public readonly string $sent_at,
        public readonly array $data,
    ) {
        //
    }
}
