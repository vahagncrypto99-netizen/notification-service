<?php

declare(strict_types=1);

namespace App\Services\Notifications\Contracts;

use App\Services\Notifications\Channels\Messenger\Dto\ResponseDto;
use App\Services\Notifications\Channels\Messenger\Dto\SenderDto;

interface MessengerSender
{
    /**
     * Отправка сообщения в мессенджер.
     */
    public function send(SenderDto $data) : ResponseDto;
}
