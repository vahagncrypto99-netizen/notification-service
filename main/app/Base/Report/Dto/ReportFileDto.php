<?php

declare(strict_types=1);

namespace App\Base\Report\Dto;

use Spatie\LaravelData\Data;

class ReportFileDto extends Data
{
    /**
     * ReportFileDto constructor.
     */
    public function __construct(
        public readonly string $absolute_path,
        public readonly string $download_name,
    ) {
        //
    }
}
