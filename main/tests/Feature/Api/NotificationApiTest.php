<?php

declare(strict_types=1);

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Jobs\SendNotificationJob;
use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;
use Illuminate\Support\Facades\Queue;

describe('создание уведомления', function () : void {
    it('создаёт уведомление в статусе processing и ставит джобу отправки', function () : void {
        Queue::fake();

        $payload = [
            'message' => 'Тестовое уведомление',
            'user_id' => 42,
            'channel' => 'email',
        ];

        $response = apiPost('/api/notifications', $payload);

        $response->assertCreated()->assertJsonPath('payload.notification.status', 'processing')->assertJsonPath(
            'payload.notification.user_id',
            42
        )->assertJsonPath('payload.notification.channel', 'email');

        $this->assertDatabaseHas('notifications', [
            'message' => 'Тестовое уведомление',
            'user_id' => 42,
            'channel' => 'email',
            'status' => 'processing',
        ]);

        Queue::assertPushed(SendNotificationJob::class, 1);
    });

    it('отклоняет сообщение длиннее 500 символов', function () : void {
        $payload = [
            'message' => str_repeat('а', 501),
            'user_id' => 42,
            'channel' => 'email',
        ];

        apiPost('/api/notifications', $payload)->assertUnprocessable()->assertJsonValidationErrors(['message'], 'payload.errors');

        expect(Notification::query()->count())->toBe(0);
    });

    it('отклоняет неизвестный канал', function () : void {
        $payload = [
            'message' => 'Привет',
            'user_id' => 42,
            'channel' => 'sms',
        ];

        apiPost('/api/notifications', $payload)->assertUnprocessable()->assertJsonValidationErrors(['channel'], 'payload.errors');
    });

    it('отклоняет запрос без обязательных полей', function () : void {
        apiPost('/api/notifications', [])->assertUnprocessable()->assertJsonValidationErrors([
            'message',
            'user_id',
            'channel',
        ], 'payload.errors');
    });
});

describe('статус уведомления', function () : void {
    it('возвращает уведомление по id', function () : void {
        $notification = Notification::factory()->sent()->create();

        apiGet("/api/notifications/{$notification->id}")->assertOk()->assertJsonPath(
            'payload.notification.id',
            $notification->id
        )->assertJsonPath('payload.notification.status', 'sent');
    });

    it('возвращает 404 для несуществующего уведомления', function () : void {
        apiGet('/api/notifications/999999')->assertNotFound();
    });
});

describe('история уведомлений', function () : void {
    it('возвращает только уведомления запрошенного пользователя', function () : void {
        Notification::factory()->count(3)->create(['user_id' => 1]);
        Notification::factory()->count(2)->create(['user_id' => 2]);

        apiGet('/api/notifications?user_id=1')->assertOk()->assertJsonCount(
            3,
            'payload.notifications'
        );
    });

    it('фильтрует по статусу', function () : void {
        Notification::factory()->count(2)->create(['user_id' => 1]);
        Notification::factory()->failed()->create(['user_id' => 1]);

        $response = apiGet('/api/notifications?user_id=1&status=failed');

        $response->assertOk()->assertJsonCount(1, 'payload.notifications')->assertJsonPath('payload.notifications.0.status', 'failed');
    });

    it('фильтрует по каналу', function () : void {
        Notification::factory()->channel(ChannelEnum::Email)->count(2)->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Telegram)->create(['user_id' => 1]);

        $response = apiGet('/api/notifications?user_id=1&channel=telegram');

        $response->assertOk()->assertJsonCount(1, 'payload.notifications')->assertJsonPath('payload.notifications.0.channel', 'telegram');
    });

    it('комбинирует фильтры по статусу и каналу', function () : void {
        Notification::factory()->channel(ChannelEnum::Email)->sent()->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Email)->failed()->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Telegram)->sent()->create(['user_id' => 1]);

        $response = apiGet('/api/notifications?user_id=1&channel=email&status=sent');

        $response->assertOk()->assertJsonCount(1, 'payload.notifications')->assertJsonPath(
            'payload.notifications.0.channel',
            'email'
        )->assertJsonPath('payload.notifications.0.status', 'sent');
    });

    it('отклоняет невалидные значения фильтров', function () : void {
        apiGet('/api/notifications?user_id=1&status=unknown')->assertUnprocessable();

        apiGet('/api/notifications?user_id=1&channel=sms')->assertUnprocessable();
    });

    it('требует user_id', function () : void {
        apiGet('/api/notifications')->assertUnprocessable()->assertJsonValidationErrors([
            'user_id',
        ], 'payload.errors');
    });
});

it('создание уведомления переживает полный цикл на sync-очереди', function () : void {
    $payload = [
        'message' => 'Сквозная проверка',
        'user_id' => 7,
        'channel' => 'telegram',
    ];

    $response = apiPost('/api/notifications', $payload);

    $response->assertCreated();

    /**
     * QUEUE_CONNECTION=sync: джоба доставки выполнилась при dispatch —
     * уведомление уже отправлено каналом.
     */
    $notification = Notification::query()->firstOrFail();

    expect($notification->status)->toBe(NotificationStatusEnum::Sent)->and(
        $notification->attempts_count
    )->toBe(1);
});

describe('контракты ответов', function () : void {
    it('пагинирует историю и отдаёт блок pagination', function () : void {
        Notification::factory()->count(20)->create(['user_id' => 55]);

        apiGet('/api/notifications?user_id=55')
            ->assertOk()
            ->assertJsonCount(15, 'payload.notifications')
            ->assertJsonPath('payload.pagination.current_page', 1)
            ->assertJsonPath('payload.pagination.per_page', 15)
            ->assertJsonPath('payload.pagination.total', 20);

        apiGet('/api/notifications?user_id=55&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'payload.notifications')
            ->assertJsonPath('payload.pagination.current_page', 2);
    });

    it('неожиданный сбой отдаёт 500 в едином контракте без утечки деталей', function () : void {
        $repository = Mockery::mock(NotificationRepository::class);

        $repository->shouldReceive('find')->andThrow(new RuntimeException('внутренние детали сбоя'));

        app()->instance(NotificationRepository::class, $repository);

        $response = apiGet('/api/notifications/1');

        $response->assertStatus(500)->assertJson([
            'success' => false,
            'message' => 'Не удалось получить уведомление.',
        ]);

        expect($response->getContent())->not->toContain('внутренние детали сбоя');
    });
});
