<?php

declare(strict_types=1);

namespace App\Domains\Notification\Enum;

enum ChannelEnum : string
{
    case Email = 'email';

    case Telegram = 'telegram';
}
