<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Services\Delivery\Messenger\Dto\ResponseDto;
use App\Services\Delivery\Messenger\Dto\SenderDto;

interface MessengerSenderInterface
{
    /**
     * Отправка сообщения в мессенджер.
     */
    public function send(SenderDto $data) : ResponseDto;
}
