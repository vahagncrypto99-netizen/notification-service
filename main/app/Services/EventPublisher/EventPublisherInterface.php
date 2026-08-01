<?php

declare(strict_types=1);

namespace App\Services\EventPublisher;

use App\Dto\EventEnvelopeDto;

interface EventPublisherInterface
{
    /**
     * Публикация события наружу.
     *
     * @throws \Throwable при сбое публикации
     */
    public function publish(EventEnvelopeDto $envelope) : void;
}
