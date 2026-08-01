<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Services\Delivery\Mail\Dto\SenderDto;

interface MailSenderInterface
{
    /**
     * Отправка письма (или постановка в очередь канала).
     */
    public function send(SenderDto $data) : void;
}
