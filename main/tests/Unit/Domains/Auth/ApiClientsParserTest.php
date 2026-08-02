<?php

declare(strict_types=1);

use App\Domains\Auth\ApiClientsParser;

it('разбирает пары токен:секрет в карту клиентов', function () : void {
    $clients = (new ApiClientsParser)->parse('svc-a:secret-of-svc-a, svc-b:secret-of-svc-b');

    expect($clients)->toBe([
        'svc-a' => 'secret-of-svc-a',
        'svc-b' => 'secret-of-svc-b',
    ]);
});

it('отклоняет пару без секрета', function () : void {
    (new ApiClientsParser)->parse('svc-a');
})->throws(RuntimeException::class);

it('отклоняет дубликат токена', function () : void {
    (new ApiClientsParser)->parse('svc-a:secret-of-svc-a,svc-a:another-secret');
})->throws(RuntimeException::class);

it('отклоняет короткий секрет', function () : void {
    (new ApiClientsParser)->parse('svc-a:short');
})->throws(RuntimeException::class);

it('отклоняет пустую конфигурацию', function () : void {
    (new ApiClientsParser)->parse('');
})->throws(RuntimeException::class);

it('пропускает поддерживаемый алгоритм и отклоняет неизвестный', function () : void {
    $parser = new ApiClientsParser;

    expect($parser->validatedAlgo('sha256'))->toBe('sha256')->and(
        fn () => $parser->validatedAlgo('md5-фейк')
    )->toThrow(RuntimeException::class);
});
