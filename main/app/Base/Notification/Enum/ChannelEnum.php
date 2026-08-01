<?php

declare(strict_types=1);

namespace App\Base\Notification\Enum;

enum ChannelEnum : string
{
    case Email = 'email';

    case Telegram = 'telegram';
}
