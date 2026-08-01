<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Unit');

beforeEach(function () : void {
    //
});

/**
 * Подписанные заголовки API для тестов (клиент из .env.testing).
 *
 * Подпись — по канонической строке, как проверяет ApiSignatureValidator:
 * hmac(secret, METHOD \n URI \n TIMESTAMP \n BODY). getJson/postJson
 * всегда шлют json_encode($data) телом (для GET без данных это "[]") —
 * подписываем ровно то, что уедет по проводу.
 *
 * @param  array<string, mixed>|null  $payload  тело будущего json-запроса
 * @return array<string, string>
 */
function apiHeaders(
    string $method,
    string $uri,
    ?array $payload = null,
    string $token = 'testing-token',
    string $secret = 'testing-secret'
) : array {
    $timestamp = (string) time();
    $body = json_encode($payload ?? []);

    $canonical = implode("\n", [strtoupper($method), $uri, $timestamp, $body]);

    return [
        'Authorization' => 'Bearer '.$token,
        'X-Timestamp' => $timestamp,
        'X-Signature' => hash_hmac((string) config('auth.api.signature_algo'), $canonical, $secret),
    ];
}

/**
 * Подписанный GET к API.
 */
function apiGet(string $uri) : TestResponse
{
    return test()->getJson($uri, apiHeaders('GET', $uri));
}

/**
 * Подписанный POST к API.
 *
 * @param  array<string, mixed>  $payload
 */
function apiPost(string $uri, array $payload) : TestResponse
{
    return test()->postJson($uri, $payload, apiHeaders('POST', $uri, $payload));
}
