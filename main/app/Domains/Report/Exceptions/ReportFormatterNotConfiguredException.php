<?php

declare(strict_types=1);

namespace App\Domains\Report\Exceptions;

use RuntimeException;

class ReportFormatterNotConfiguredException extends RuntimeException
{
    /**
     * ReportFormatterNotConfiguredException constructor.
     */
    public function __construct(string $format)
    {
        parent::__construct(
            "Для формата отчёта '{$format}' не зарегистрирован форматтер."
        );
    }
}
