<?php

declare(strict_types=1);

namespace App\Base\Notification\Reports;

interface ReportFormatterInterface
{
    /**
     * Собрать содержимое файла отчёта из строк агрегации.
     *
     * @param  array<int, array{channel: string, total: int, failed: int}>  $rows
     */
    public function format(array $rows) : string;

    /**
     * Расширение файла отчёта (без точки).
     */
    public function extension() : string;
}
