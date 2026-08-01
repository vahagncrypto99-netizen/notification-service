<?php

declare(strict_types=1);

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Jobs\SendNotificationJob;
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

        $response->assertCreated()->assertJsonPath('data.status', 'processing')->assertJsonPath(
            'data.user_id',
            42
        )->assertJsonPath('data.channel', 'email');

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

        apiPost('/api/notifications', $payload)->assertUnprocessable()->assertJsonValidationErrors(['message']);

        expect(Notification::query()->count())->toBe(0);
    });

    it('отклоняет неизвестный канал', function () : void {
        $payload = [
            'message' => 'Привет',
            'user_id' => 42,
            'channel' => 'sms',
        ];

        apiPost('/api/notifications', $payload)->assertUnprocessable()->assertJsonValidationErrors(['channel']);
    });

    it('отклоняет запрос без обязательных полей', function () : void {
        apiPost('/api/notifications', [])->assertUnprocessable()->assertJsonValidationErrors([
            'message',
            'user_id',
            'channel',
        ]);
    });
});

describe('статус уведомления', function () : void {
    it('возвращает уведомление по id', function () : void {
        $notification = Notification::factory()->sent()->create();

        apiGet("/api/notifications/{$notification->id}")->assertOk()->assertJsonPath(
            'data.id',
            $notification->id
        )->assertJsonPath('data.status', 'sent');
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
            'data'
        );
    });

    it('фильтрует по статусу', function () : void {
        Notification::factory()->count(2)->create(['user_id' => 1]);
        Notification::factory()->failed()->create(['user_id' => 1]);

        $response = apiGet('/api/notifications?user_id=1&status=failed');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'failed');
    });

    it('фильтрует по каналу', function () : void {
        Notification::factory()->channel(ChannelEnum::Email)->count(2)->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Telegram)->create(['user_id' => 1]);

        $response = apiGet('/api/notifications?user_id=1&channel=telegram');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.channel', 'telegram');
    });

    it('комбинирует фильтры по статусу и каналу', function () : void {
        Notification::factory()->channel(ChannelEnum::Email)->sent()->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Email)->failed()->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Telegram)->sent()->create(['user_id' => 1]);

        $response = apiGet('/api/notifications?user_id=1&channel=email&status=sent');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath(
            'data.0.channel',
            'email'
        )->assertJsonPath('data.0.status', 'sent');
    });

    it('отклоняет невалидные значения фильтров', function () : void {
        apiGet('/api/notifications?user_id=1&status=unknown')->assertUnprocessable();

        apiGet('/api/notifications?user_id=1&channel=sms')->assertUnprocessable();
    });

    it('требует user_id', function () : void {
        apiGet('/api/notifications')->assertUnprocessable()->assertJsonValidationErrors([
            'user_id',
        ]);
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
     * QUEUE_CONNECTION=sync: джоба выполняется сразу после коммита —
     * уведомление в БД уже отправлено.
     */
    $notification = Notification::query()->firstOrFail();

    expect($notification->status)->toBe(NotificationStatusEnum::Sent)->and(
        $notification->attempts_count
    )->toBe(1);
});
