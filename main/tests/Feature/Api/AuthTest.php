<?php

declare(strict_types=1);

use App\Models\Notification;

it('отклоняет запрос без токена', function () : void {
    $this->getJson('/api/notifications?user_id=1')->assertUnauthorized()->assertJson([
        'message' => 'Unauthenticated.',
    ]);
});

it('отклоняет запрос с невалидным токеном', function () : void {
    $this->getJson('/api/notifications?user_id=1', [
        'Authorization' => 'Bearer wrong-token',
    ])->assertUnauthorized();
});

it('пропускает запрос с валидным токеном', function () : void {
    $this->getJson('/api/notifications?user_id=1', apiHeaders())->assertOk();
});

it('принимает каждый из настроенных токенов', function () : void {
    $this->getJson('/api/notifications?user_id=1', [
        'Authorization' => 'Bearer second-testing-token',
    ])->assertOk();
});

it('отклоняет отозванный токен', function () : void {
    config(['auth.api.tokens' => 'brand-new-token']);

    $this->getJson('/api/notifications?user_id=1', apiHeaders())->assertUnauthorized();
});

it('не выполняет доменную логику без токена', function () : void {
    $this->postJson('/api/notifications', [
        'message' => 'Привет',
        'user_id' => 1,
        'channel' => 'email',
    ])->assertUnauthorized();

    expect(Notification::query()->count())->toBe(0);
});
