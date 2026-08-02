<?php

declare(strict_types=1);

use App\Services\Delivery\Mail\DefaultSender;
use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\Mail\SenderFactory;
use App\Services\Delivery\PermanentDeliveryException;

describe('фабрика сендеров', function () : void {
    it('отдаёт дефолтный сендер по конфигу', function () : void {
        expect(app(SenderFactory::class)->mail())->toBeInstanceOf(DefaultSender::class);
    });

    it('бросает исключение для неизвестного сендера', function () : void {
        app(SenderFactory::class)->mail('unisender');
    })->throws(RuntimeException::class);
});

describe('отправка письма', function () : void {
    it('невалидный адрес получателя — неисправимый отказ', function () : void {
        expect(fn () => app(SenderFactory::class)->mail()->send(
            SenderDto::from([
                'from_email' => null,
                'from_name' => null,
                'to_email' => 'не-адрес',
                'subject' => 'x',
                'message' => 'y',
            ])
        ))->toThrow(PermanentDeliveryException::class);
    });
});
