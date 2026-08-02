<?php

declare(strict_types=1);

namespace App\Domains\Notification\Exceptions;

use App\Domains\Notification\Enum\ChannelEnum;
use RuntimeException;

class ChannelSenderNotConfiguredException extends RuntimeException
{
    /**
     * ChannelSenderNotConfiguredException constructor.
     */
    public function __construct(ChannelEnum $channel)
    {
        parent::__construct(
            "Для канала '{$channel->value}' не зарегистрирован отправитель."
        );
    }
}
