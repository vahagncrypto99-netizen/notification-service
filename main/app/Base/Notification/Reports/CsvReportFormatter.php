<?php

declare(strict_types=1);

namespace App\Base\Notification\Reports;

class CsvReportFormatter implements ReportFormatterInterface
{
    /**
     * Собрать содержимое файла отчёта из строк агрегации.
     *
     * @param  array<int, array{channel: string, total: int, failed: int}>  $rows
     */
    public function format(array $rows) : string
    {
        $csv_lines = ['channel,total,failed'];

        foreach ($rows as $row) {
            $csv_lines[] = "{$row['channel']},{$row['total']},{$row['failed']}";
        }

        return implode("\n", $csv_lines)."\n";
    }

    /**
     * Расширение файла отчёта (без точки).
     */
    public function extension() : string
    {
        return 'csv';
    }
}
