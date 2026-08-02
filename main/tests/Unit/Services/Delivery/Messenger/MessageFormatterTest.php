<?php

declare(strict_types=1);

use App\Services\Delivery\Messenger\MessageFormatter;

it('включает смайлы из literal-последовательностей', function () : void {
    expect(
        app(MessageFormatter::class)->prepareMessage('Привет 😊')
    )->toBe('Привет 😊');
});

it('вырезает html-теги', function () : void {
    expect(
        app(MessageFormatter::class)->prepareMessage('<b>жирный</b> текст')
    )->toBe('жирный текст');
});

it('удаляет управляющие символы', function () : void {
    expect(
        app(MessageFormatter::class)->prepareMessage("чистый\x00\x1Fтекст")
    )->toBe('чистыйтекст');
});

it('непарный суррогат не роняет доставку — возвращается исходный текст', function () : void {
    expect(
        app(MessageFormatter::class)->prepareMessage('Привет \uD83D')
    )->toBe('Привет \uD83D');
});
