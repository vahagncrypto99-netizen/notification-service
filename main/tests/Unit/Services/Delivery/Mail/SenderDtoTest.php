<?php

declare(strict_types=1);

use App\Services\Delivery\Mail\Dto\SenderDto;

function mailDto(array $overrides = []) : SenderDto
{
    return SenderDto::from(array_merge([
        'from_email' => null,
        'from_name' => null,
        'to_email' => 'user@example.stub',
        'subject' => 'Тема',
        'message' => 'Текст',
    ], $overrides));
}

it('пустой from заполняется дефолтным отправителем из конфига', function () : void {
    $dto = mailDto();

    expect($dto->from_email)->toBe(config('delivery.mail.from.default.email'))->and(
        $dto->from_name
    )->toBe(config('delivery.mail.from.default.name'));
});

it('from из разрешённого списка уходит как есть, без Reply-To', function () : void {
    $address = mailDto([
        'from_email' => config('delivery.mail.from.default.email'),
    ])->getFromAddress();

    expect($address->from_email)->toBe(config('delivery.mail.from.default.email'))->and(
        $address->reply_to
    )->toBeNull();
});

it('from вне разрешённого списка заменяется дефолтным, оригинал — в Reply-To', function () : void {
    $address = mailDto(['from_email' => 'partner@external.example'])->getFromAddress();

    expect($address->from_email)->toBe(config('delivery.mail.from.default.email'))->and(
        $address->reply_to
    )->toBe('partner@external.example');
});

it('высокий приоритет определяется порогом', function () : void {
    expect(mailDto(['priority' => 10])->isHighPriority())->toBeTrue()->and(
        mailDto(['priority' => 1])->isHighPriority()
    )->toBeFalse();
});
