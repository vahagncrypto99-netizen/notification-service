<?php

declare(strict_types=1);

namespace App\Services\EventPublisher;

use App\Dto\EventEnvelopeDto;

class NullEventPublisher implements EventPublisherInterface
{
    /**
     * Публикация отключена конфигом (NOTIFICATION_EVENTS_ENABLED=false):
     * тестовое окружение и локальная разработка без внешних подписчиков.
     */
    public function publish(EventEnvelopeDto $envelope) : void
    {
        //
    }
}
