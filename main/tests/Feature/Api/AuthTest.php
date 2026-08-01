<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Services\ApiSignatureValidator;

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
    $headers = apiHeaders('GET', '/api/notifications?user_id=1');
    $headers['X-Signature'] = hash_hmac('sha256', 'подделка', 'wrong-secret');

    $this->getJson('/api/notifications?user_id=1', $headers)->assertUnauthorized();
});

it('отклоняет подпись чужим секретом при валидном токене', function () : void {
    /**
     * Сервис знает чужой токен, но не владеет его секретом —
     * подписывает своим. Запрос не проходит.
     */
    $headers = apiHeaders(
        'GET',
        '/api/notifications?user_id=1',
        token: 'testing-token',
        secret: 'second-testing-secret'
    );

    $this->getJson('/api/notifications?user_id=1', $headers)->assertUnauthorized();
});

it('отклоняет протухший timestamp (replay-защита)', function () : void {
    $stale = (string) (time() - 3600);
    $canonical = implode("\n", ['GET', '/api/notifications?user_id=1', $stale, '[]']);

    $this->getJson('/api/notifications?user_id=1', [
        'Authorization' => 'Bearer testing-token',
        'X-Timestamp' => $stale,
        'X-Signature' => hash_hmac('sha256', $canonical, 'testing-secret'),
    ])->assertUnauthorized();
});

it('подпись привязана к URI: валидная подпись другого запроса не проходит', function () : void {
    /**
     * Перехваченная подпись GET /api/notifications?user_id=1 не даёт
     * читать данные другого пользователя или другой эндпоинт.
     */
    $headers = apiHeaders('GET', '/api/notifications?user_id=1');

    $this->getJson('/api/notifications?user_id=2', $headers)->assertUnauthorized();
    $this->getJson('/api/reports/1', $headers)->assertUnauthorized();
});

it('подпись привязана к телу: чужое тело с валидной подписью не проходит', function () : void {
    $signed_for_other_body = apiHeaders('POST', '/api/notifications', [
        'message' => 'оригинал',
        'user_id' => 1,
        'channel' => 'email',
    ]);

    $this->postJson('/api/notifications', [
        'message' => 'подменённое тело',
        'user_id' => 999,
        'channel' => 'email',
    ], $signed_for_other_body)->assertUnauthorized();

    expect(Notification::query()->count())->toBe(0);
});

it('пропускает корректно подписанный запрос', function () : void {
    apiGet('/api/notifications?user_id=1')->assertOk();
});

it('пропускает каждого из настроенных клиентов с его секретом', function () : void {
    $headers = apiHeaders(
        'GET',
        '/api/notifications?user_id=1',
        token: 'second-testing-token',
        secret: 'second-testing-secret'
    );

    $this->getJson('/api/notifications?user_id=1', $headers)->assertOk();
});

it('отклоняет отозванного клиента', function () : void {
    config(['auth.api.clients' => 'brand-new-token:brand-new-secret']);

    /**
     * Валидатор — singleton, собранный из конфига на старте:
     * смена конфига в тесте требует пересборки.
     */
    app()->forgetInstance(ApiSignatureValidator::class);

    apiGet('/api/notifications?user_id=1')->assertUnauthorized();
});

it('отклоняет повтор уже принятой подписи (replay)', function () : void {
    $uri = '/api/notifications?user_id=1';
    $headers = apiHeaders('GET', $uri);

    $this->getJson($uri, $headers)->assertOk();

    /**
     * Тот же запрос с теми же заголовками внутри окна TTL —
     * nonce уже израсходован.
     */
    $this->getJson($uri, $headers)->assertUnauthorized();
});

it('отклоняет запрос без nonce', function () : void {
    $uri = '/api/notifications?user_id=1';
    $headers = apiHeaders('GET', $uri);

    unset($headers['X-Nonce']);

    $this->getJson($uri, $headers)->assertUnauthorized();
});

it('не выполняет доменную логику без аутентификации', function () : void {
    $this->postJson('/api/notifications', [
        'message' => 'Привет',
        'user_id' => 1,
        'channel' => 'email',
    ])->assertUnauthorized();

    expect(Notification::query()->count())->toBe(0);
});
