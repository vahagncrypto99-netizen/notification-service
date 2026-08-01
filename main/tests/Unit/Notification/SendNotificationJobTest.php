<?php

declare(strict_types=1);

use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Events\NotificationFailed;
use App\Base\Notification\Events\NotificationSent;
use App\Base\Notification\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Прямой вызов handle с DI-зависимостями из контейнера.
 */
function runSendJob(int $notification_id) : void
{
    app()->call([new SendNotificationJob($notification_id), 'handle']);
}

it('успешная отправка переводит уведомление в sent и диспатчит событие', function () : void {
    Event::fake([NotificationSent::class]);

    $notification = Notification::factory()->create();

    runSendJob($notification->id);

    $fresh = $notification->fresh();

    expect($fresh->status)->toBe(NotificationStatusEnum::Sent)->and(
        $fresh->attempts_count
    )->toBe(1)->and($fresh->last_attempted_at)->not->toBeNull();

    Event::assertDispatched(NotificationSent::class);
});

it('сбой отправки фиксирует ошибку и пробрасывает исключение для ретрая', function () : void {
    config(['delivery.simulate_failures' => true]);

    $notification = Notification::factory()->create();

    expect(fn () => runSendJob($notification->id))->toThrow(RuntimeException::class);

    $fresh = $notification->fresh();

    expect($fresh->status)->toBe(NotificationStatusEnum::Processing)->and(
        $fresh->attempts_count
    )->toBe(1)->and($fresh->last_error)->toContain('Симулированный сбой');
});

it('восстановление после сбоя: повторная попытка доводит до sent', function () : void {
    config(['delivery.simulate_failures' => true]);

    $notification = Notification::factory()->create();

    expect(fn () => runSendJob($notification->id))->toThrow(RuntimeException::class);

    config(['delivery.simulate_failures' => false]);

    runSendJob($notification->id);

    $fresh = $notification->fresh();

    expect($fresh->status)->toBe(NotificationStatusEnum::Sent)->and(
        $fresh->attempts_count
    )->toBe(2);
});

it('исчерпание попыток переводит уведомление в failed и диспатчит событие', function () : void {
    Event::fake([NotificationFailed::class]);

    $notification = Notification::factory()->create();

    (new SendNotificationJob($notification->id))->failed(
        new RuntimeException('Провайдер недоступен')
    );

    $fresh = $notification->fresh();

    expect($fresh->status)->toBe(NotificationStatusEnum::Failed)->and(
        $fresh->last_error
    )->toBe('Провайдер недоступен');

    Event::assertDispatched(NotificationFailed::class);
});

it('не отправляет повторно уже отправленное уведомление', function () : void {
    $notification = Notification::factory()->sent()->create(['attempts_count' => 1]);

    runSendJob($notification->id);

    $fresh = $notification->fresh();

    expect($fresh->status)->toBe(NotificationStatusEnum::Sent)->and(
        $fresh->attempts_count
    )->toBe(1);
});

describe('watchdog', function () : void {
    it('передиспатчивает уведомления, зависшие в processing дольше порога', function () : void {
        Queue::fake();

        $stuck = Notification::factory()->create();

        Notification::query()->whereKey($stuck->id)->update([
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->artisan('notification:redispatch-stuck')->assertSuccessful();

        Queue::assertPushed(SendNotificationJob::class, 1);
    });

    it('не трогает свежие processing-уведомления и терминальные статусы', function () : void {
        Queue::fake();

        Notification::factory()->create();
        Notification::factory()->sent()->create();

        $old_failed = Notification::factory()->failed()->create();

        Notification::query()->whereKey($old_failed->id)->update([
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->artisan('notification:redispatch-stuck')->assertSuccessful();

        Queue::assertNothingPushed();
    });

    it('после передиспатча сбрасывает часы зависания', function () : void {
        Queue::fake();

        $stuck = Notification::factory()->create();

        Notification::query()->whereKey($stuck->id)->update([
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->artisan('notification:redispatch-stuck')->assertSuccessful();

        expect(
            $stuck->fresh()->updated_at->greaterThan(now()->subMinute())
        )->toBeTrue();

        /**
         * Повторный запуск сразу после — ничего не передиспатчивает.
         */
        $this->artisan('notification:redispatch-stuck')->assertSuccessful();

        Queue::assertPushed(SendNotificationJob::class, 1);
    });
});

describe('watchdog: дренаж', function () : void {
    it('дренирует бэклог больше одной пачки за один запуск', function () : void {
        Queue::fake();

        config(['notification.watchdog.batch_limit' => 2]);

        Notification::factory()->count(5)->create();

        Notification::query()->update(['updated_at' => now()->subMinutes(30)]);

        $this->artisan('notification:redispatch-stuck')->assertSuccessful();

        Queue::assertPushed(SendNotificationJob::class, 5);
    });
});
