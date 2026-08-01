<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Services\Delivery\Mail\Dto\SenderDto;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DefaultSender extends Sender
{
    /**
     * Название сендера.
     */
    protected function getSenderName() : string
    {
        return 'default';
    }

    /**
     * Отправка письма. По условию задания реальной отправки нет —
     * вместо Mail::send письмо фиксируется в логе с полным набором
     * заголовков (from/reply-to считаются как в боевой реализации).
     */
    protected function sendProcess(SenderDto $data) : void
    {
        if (config('delivery.simulate_failures')) {
            throw new RuntimeException('Симулированный сбой отправки email.');
        }

        $from_address = $data->getFromAddress();

        Log::info("[default] Письмо отправлено: {$data->to_email}.", [
            'from' => "{$from_address->from_name} <{$from_address->from_email}>",
            'reply_to' => $from_address->reply_to,
            'subject' => $data->subject,
            'high_priority' => $data->isHighPriority(),
        ]);
    }
}
