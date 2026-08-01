<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Unit');

beforeEach(function () : void {
    //
});

/**
 * Подписанные заголовки API для тестов (клиент из .env.testing).
 *
 * Подпись считается по той же схеме, что проверяет ApiSignatureAuth:
 * hmac_sha256(secret, timestamp . "." . body). Для GET тело пустое.
 *
 * @param  array<string, mixed>|null  $payload  тело будущего json-запроса
 * @return array<string, string>
 */
function apiHeaders(
    ?array $payload = null,
    string $token = 'testing-token',
    string $secret = 'testing-secret'
) : array {
    $timestamp = (string) time();

    /**
     * getJson/postJson всегда шлют json_encode($data) телом
     * (для GET без данных это "[]") — подписываем ровно то,
     * что уедет по проводу.
     */
    $body = json_encode($payload ?? []);

    return [
        'Authorization' => 'Bearer '.$token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
    ];
}
