<?php

declare(strict_types=1);

namespace App\Base\Notification\Enum;

enum NotificationStatusEnum : string
{
    case Processing = 'processing';

    case Sent = 'sent';

    case Failed = 'failed';
}
