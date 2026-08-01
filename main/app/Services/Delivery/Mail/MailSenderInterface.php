<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\PermanentDeliveryException;

interface MailSenderInterface
{
    /**
     * Отправка письма.
     *
     * @throws PermanentDeliveryException при неисправимом отказе
     * @throws \Throwable при транзиентном сбое
     */
    public function send(SenderDto $data) : void;
}
