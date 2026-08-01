<?php

declare(strict_types=1);

namespace App\Base\Notification\Exceptions;

use App\Base\Notification\Enum\ChannelEnum;
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
