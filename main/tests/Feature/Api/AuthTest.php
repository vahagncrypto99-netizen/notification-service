<?php

declare(strict_types=1);

use App\Models\Notification;

it('отклоняет запрос без заголовков аутентификации', function () : void {
    $this->getJson('/api/notifications?user_id=1')->assertUnauthorized()->assertJson([
        'message' => 'Unauthenticated.',
    ]);
});

it('отклоняет токен без подписи', function () : void {
    $this->getJson('/api/notifications?user_id=1', [
        'Authorization' => 'Bearer testing-token',
    ])->assertUnauthorized();
});

it('отклоняет невалидную подпись', function () : void {
    $headers = apiHeaders();
    $headers['X-Signature'] = hash_hmac('sha256', 'подделка', 'wrong-secret');

    $this->getJson('/api/notifications?user_id=1', $headers)->assertUnauthorized();
});

it('отклоняет подпись чужим секретом при валидном токене', function () : void {
    /**
     * Сервис знает чужой токен, но не владеет его секретом —
     * подписывает своим. Запрос не проходит.
     */
    $headers = apiHeaders(token: 'testing-token', secret: 'second-testing-secret');

    $this->getJson('/api/notifications?user_id=1', $headers)->assertUnauthorized();
});

it('отклоняет протухший timestamp (replay-защита)', function () : void {
    $stale = (string) (time() - 3600);

    $this->getJson('/api/notifications?user_id=1', [
        'Authorization' => 'Bearer testing-token',
        'X-Timestamp' => $stale,
        'X-Signature' => hash_hmac('sha256', $stale.'.', 'testing-secret'),
    ])->assertUnauthorized();
});

it('пропускает корректно подписанный запрос', function () : void {
    $this->getJson('/api/notifications?user_id=1', apiHeaders())->assertOk();
});

it('пропускает каждого из настроенных клиентов с его секретом', function () : void {
    $headers = apiHeaders(token: 'second-testing-token', secret: 'second-testing-secret');

    $this->getJson('/api/notifications?user_id=1', $headers)->assertOk();
});

it('отклоняет отозванного клиента', function () : void {
    config(['auth.api.clients' => 'brand-new-token:brand-new-secret']);

    $this->getJson('/api/notifications?user_id=1', apiHeaders())->assertUnauthorized();
});

it('подпись привязана к телу: чужое тело с валидной подписью не проходит', function () : void {
    $signed_for_other_body = apiHeaders(['message' => 'оригинал', 'user_id' => 1, 'channel' => 'email']);

    $this->postJson('/api/notifications', [
        'message' => 'подменённое тело',
        'user_id' => 999,
        'channel' => 'email',
    ], $signed_for_other_body)->assertUnauthorized();

    expect(Notification::query()->count())->toBe(0);
});

it('не выполняет доменную логику без аутентификации', function () : void {
    $this->postJson('/api/notifications', [
        'message' => 'Привет',
        'user_id' => 1,
        'channel' => 'email',
    ])->assertUnauthorized();

    expect(Notification::query()->count())->toBe(0);
});
