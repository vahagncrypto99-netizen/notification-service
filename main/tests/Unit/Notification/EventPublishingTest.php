<?php

declare(strict_types=1);

use App\Base\Notification\Jobs\SendNotificationJob;
use App\Dto\EventEnvelopeDto;
use App\Models\Notification;
use App\Services\EventPublisher\EventEnvelopeFactory;
use App\Services\EventPublisher\EventPublisherInterface;
use Illuminate\Support\Str;

/**
 * Fake-паблишер: собирает конверты вместо отправки в брокер.
 */
function fakeEventPublisher() : EventPublisherInterface
{
    $fake = new class implements EventPublisherInterface
    {
        /** @var array<int, EventEnvelopeDto> */
        public array $published = [];

        public function publish(EventEnvelopeDto $envelope) : void
        {
            $this->published[] = $envelope;
        }
    };

    app()->instance(EventPublisherInterface::class, $fake);

    return $fake;
}

it('конверт события версионирован и содержит издателя', function () : void {
    $envelope = app(EventEnvelopeFactory::class)->make('notification.sent', ['notification_id' => 7]);

    expect(Str::isUuid($envelope->uuid))->toBeTrue()->and(
        $envelope->type
    )->toBe('notification.sent')->and(
        $envelope->publisher
    )->toBe(config('app.name').'.'.config('app.env'))->and(
        $envelope->version
    )->toBe(1)->and($envelope->data)->toBe(['notification_id' => 7]);
});

it('успешная доставка публикует конверт notification.sent', function () : void {
    $fake = fakeEventPublisher();

    $notification = Notification::factory()->create();

    app()->call([new SendNotificationJob($notification->id), 'handle']);

    deliverChannels();

    expect($fake->published)->toHaveCount(1);

    $envelope = $fake->published[0];

    expect($envelope->type)->toBe('notification.sent')->and(
        $envelope->data['notification_id']
    )->toBe($notification->id)->and($envelope->data['status'])->toBe('sent');
});

it('исчерпание попыток публикует конверт notification.failed', function () : void {
    $fake = fakeEventPublisher();

    $notification = Notification::factory()->create();

    (new SendNotificationJob($notification->id))->failed(
        new RuntimeException('Провайдер недоступен')
    );

    expect($fake->published)->toHaveCount(1);

    $envelope = $fake->published[0];

    expect($envelope->type)->toBe('notification.failed')->and(
        $envelope->data['error']
    )->toBe('Провайдер недоступен');
});

it('текст уведомления не попадает в конверт', function () : void {
    $fake = fakeEventPublisher();

    $notification = Notification::factory()->create(['message' => 'секретное содержимое']);

    app()->call([new SendNotificationJob($notification->id), 'handle']);

    deliverChannels();

    expect(json_encode($fake->published[0]->data))->not->toContain('секретное содержимое');
});

it('сбой публикации не ломает доставку', function () : void {
    app()->instance(EventPublisherInterface::class, new class implements EventPublisherInterface
    {
        public function publish(EventEnvelopeDto $envelope) : void
        {
            throw new RuntimeException('Брокер недоступен для публикации');
        }
    });

    $notification = Notification::factory()->create();

    app()->call([new SendNotificationJob($notification->id), 'handle']);

    deliverChannels();

    expect($notification->fresh()->status->value)->toBe('sent');
});
