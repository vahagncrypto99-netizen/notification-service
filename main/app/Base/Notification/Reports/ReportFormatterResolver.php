<?php

declare(strict_types=1);

namespace App\Base\Notification\Reports;

use App\Base\Notification\Exceptions\ReportFormatterNotConfiguredException;

class ReportFormatterResolver
{
    /**
     * Получение стратегии форматирования для настроенного типа отчёта.
     *
     * @throws ReportFormatterNotConfiguredException
     */
    public function resolve() : ReportFormatterInterface
    {
        $format = (string) config('notification.reports.format');

        $map = config('notification.reports.formatters', []);

        $formatter_class = $map[$format] ?? null;

        if ($formatter_class === null || ! is_subclass_of($formatter_class, ReportFormatterInterface::class)) {
            throw new ReportFormatterNotConfiguredException($format);
        }

        return app($formatter_class);
    }
}
