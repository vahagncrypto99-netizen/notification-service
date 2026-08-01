<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\PermanentDeliveryException;

abstract class Sender implements MailSenderInterface
{
    /**
     * Отправка письма.
     *
     * Ошибки не подавляются: вызывающая джоба сама решает —
     * ретрай или терминальный отказ.
     *
     * @throws PermanentDeliveryException при невалидном адресе получателя
     */
    public function send(SenderDto $data) : void
    {
        /**
         * Невалидный адрес — неисправимый отказ, ретраи бессмысленны
         * (в боевой реализации здесь же — отписка/блэклист и метрики).
         */
        if (filter_var($data->to_email, FILTER_VALIDATE_EMAIL) === false) {
            throw new PermanentDeliveryException("Невалидный адрес получателя: {$data->to_email}.");
        }

        $this->sendProcess($data);
    }

    /**
     * Отправка письма конкретным сендером.
     */
    abstract protected function sendProcess(SenderDto $data) : void;
}
