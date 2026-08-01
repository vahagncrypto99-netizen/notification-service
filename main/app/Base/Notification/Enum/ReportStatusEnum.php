<?php

declare(strict_types=1);

namespace App\Base\Notification\Enum;

enum ReportStatusEnum : string
{
    case Pending = 'pending';

    case Processing = 'processing';

    case Done = 'done';

    case Failed = 'failed';
}
