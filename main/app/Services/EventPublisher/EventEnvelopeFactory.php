<?php

declare(strict_types=1);

namespace App\Services\EventPublisher;

use App\Dto\EventEnvelopeDto;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EventEnvelopeFactory
{
    /**
     * EventEnvelopeFactory constructor.
     *
     * @param  string  $publisher  идентификатор сервиса-издателя ("имя.окружение")
     * @param  int  $version  версия схемы конверта
     */
    public function __construct(
        private readonly string $publisher,
        private readonly int $version,
    ) {
        //
    }

    /**
     * Собрать версионированный конверт события.
     *
     * @param  array<string, mixed>  $data
     */
    public function make(string $type, array $data) : EventEnvelopeDto
    {
        return new EventEnvelopeDto(
            uuid: (string) Str::uuid(),
            type: $type,
            publisher: $this->publisher,
            version: $this->version,
            sent_at: Carbon::now()->toIso8601String(),
            data: $data,
        );
    }
}
