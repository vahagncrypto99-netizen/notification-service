<?php

declare(strict_types=1);

namespace App\Services\Notifications\Contracts;

use App\Services\Notifications\Channels\Mail\Dto\SenderDto;

interface MailSender
{
    /**
     * Отправка письма (или постановка в очередь канала).
     */
    public function send(SenderDto $data) : void;
}
