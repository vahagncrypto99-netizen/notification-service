<?php

declare(strict_types=1);

namespace App\Base\Notification\Services;

use App\Base\Notification\Reports\ReportFormatterResolver;
use Illuminate\Contracts\Filesystem\Filesystem;

class ReportFileStorage
{
    /**
     * ReportFileStorage constructor.
     */
    public function __construct(
        private readonly ReportFormatterResolver $formatter_resolver,
        private readonly Filesystem $disk
    ) {
        //
    }

    /**
     * Записать отчёт атомарно: содержимое собирает стратегия формата,
     * файл пишется во временный путь с переносом на постоянный —
     * частичный файл никогда не окажется на месте готового.
     *
     * @param  array<int, array{channel: string, total: int, failed: int}>  $rows
     * @return string постоянный путь файла
     */
    public function write(int $report_id, array $rows) : string
    {
        $formatter = $this->formatter_resolver->resolve();

        $tmp_path = $this->tmpPath($report_id, $formatter->extension());
        $final_path = $this->finalPath($report_id, $formatter->extension());

        $this->disk->put($tmp_path, $formatter->format($rows));

        $this->disk->delete($final_path);
        $this->disk->move($tmp_path, $final_path);

        return $final_path;
    }

    /**
     * Убрать недописанный временный файл упавшей генерации.
     */
    public function deleteTemp(int $report_id) : void
    {
        $extension = $this->formatter_resolver->resolve()->extension();

        $this->disk->delete($this->tmpPath($report_id, $extension));
    }

    /**
     * Временный путь файла отчёта.
     */
    private function tmpPath(int $report_id, string $extension) : string
    {
        $directory = config('notification.reports.directory');

        return "{$directory}/tmp/report_{$report_id}.{$extension}.tmp";
    }

    /**
     * Постоянный путь файла отчёта.
     */
    private function finalPath(int $report_id, string $extension) : string
    {
        $directory = config('notification.reports.directory');

        return "{$directory}/report_{$report_id}.{$extension}";
    }
}
