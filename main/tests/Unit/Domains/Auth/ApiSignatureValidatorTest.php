<?php

declare(strict_types=1);

use App\Domains\Auth\ApiSignatureValidator;
use App\Domains\Auth\Dto\RequestSignatureDto;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

/**
 * Чистый юнит: валидатор собирается руками, без контейнера и HTTP.
 */
function makeValidator(array $clients = ['unit-token' => 'unit-secret-value']) : ApiSignatureValidator
{
    return new ApiSignatureValidator($clients, 300, 'sha256', new CacheRepository(new ArrayStore));
}

/**
 * Корректно подписанный DTO запроса (с возможностью испортить поля).
 *
 * @param  array<string, string>  $overrides
 */
function signedDto(array $overrides = [], string $secret = 'unit-secret-value') : RequestSignatureDto
{
    $fields = array_merge([
        'token' => 'unit-token',
        'timestamp' => (string) time(),
        'nonce' => (string) Str::uuid(),
        'method' => 'POST',
        'uri' => '/api/notifications',
        'body' => '{"message":"x"}',
    ], $overrides);

    $canonical = implode("\n", [
        strtoupper($fields['method']),
        $fields['uri'],
        $fields['timestamp'],
        $fields['nonce'],
        $fields['body'],
    ]);

    return new RequestSignatureDto(
        token: $fields['token'],
        timestamp: $fields['timestamp'],
        nonce: $fields['nonce'],
        signature: $overrides['signature'] ?? hash_hmac('sha256', $canonical, $secret),
        method: $fields['method'],
        uri: $fields['uri'],
        body: $fields['body'],
    );
}

it('принимает корректно подписанный запрос', function () : void {
    expect(makeValidator()->validate(signedDto()))->toBeTrue();
});

it('отклоняет повтор того же nonce (replay)', function () : void {
    $validator = makeValidator();
    $dto = signedDto();

    expect($validator->validate($dto))->toBeTrue()->and(
        $validator->validate($dto)
    )->toBeFalse();
});

it('отклоняет нецифровой timestamp', function () : void {
    expect(makeValidator()->validate(signedDto(['timestamp' => 'abc'])))->toBeFalse();
});

it('отклоняет timestamp из будущего за пределами окна', function () : void {
    expect(
        makeValidator()->validate(signedDto(['timestamp' => (string) (time() + 3600)]))
    )->toBeFalse();
});

it('отклоняет неизвестный токен', function () : void {
    expect(makeValidator()->validate(signedDto(['token' => 'stranger'])))->toBeFalse();
});

it('отклоняет подпись чужим секретом', function () : void {
    expect(
        makeValidator()->validate(signedDto(secret: 'someone-else-secret'))
    )->toBeFalse();
});

it('отклоняет всё при пустой карте клиентов', function () : void {
    expect(makeValidator([])->validate(signedDto()))->toBeFalse();
});

it('знает свои токены для ключа rate limiting-а', function () : void {
    $validator = makeValidator();

    expect($validator->knownToken('unit-token'))->toBeTrue()->and(
        $validator->knownToken('stranger')
    )->toBeFalse();
});
