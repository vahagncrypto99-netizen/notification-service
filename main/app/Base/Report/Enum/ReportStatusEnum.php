<?php

declare(strict_types=1);

namespace App\Base\Report\Enum;

enum ReportStatusEnum : string
{
    case Pending = 'pending';

    case Processing = 'processing';

    case Done = 'done';

    case Failed = 'failed';
}
