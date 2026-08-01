<?php

declare(strict_types=1);

it('ограничивает число запросов клиента в минуту', function () : void {
    config(['auth.api.rate_limit' => 3]);

    foreach (range(1, 3) as $i) {
        apiGet('/api/notifications?user_id=1')->assertOk();
    }

    apiGet('/api/notifications?user_id=1')->assertStatus(429)->assertJson([
        'success' => false,
        'message' => 'Слишком много запросов.',
    ]);
});

it('лимит одного клиента не задевает другого', function () : void {
    config(['auth.api.rate_limit' => 2]);

    foreach (range(1, 2) as $i) {
        apiGet('/api/notifications?user_id=1')->assertOk();
    }

    apiGet('/api/notifications?user_id=1')->assertStatus(429);

    /**
     * Второй клиент — свой ключ лимита, запросы проходят.
     */
    $headers = apiHeaders(
        'GET',
        '/api/notifications?user_id=1',
        token: 'second-testing-token',
        secret: 'second-testing-secret'
    );

    $this->getJson('/api/notifications?user_id=1', $headers)->assertOk();
});

it('ротация фейковых токенов не обходит лимит: неизвестные токены считаются по IP', function () : void {
    config(['auth.api.rate_limit' => 3]);

    foreach (range(1, 3) as $i) {
        $this->getJson('/api/notifications?user_id=1', [
            'Authorization' => 'Bearer random-token-'.$i,
        ])->assertUnauthorized();
    }

    $this->getJson('/api/notifications?user_id=1', [
        'Authorization' => 'Bearer random-token-4',
    ])->assertStatus(429);
});
