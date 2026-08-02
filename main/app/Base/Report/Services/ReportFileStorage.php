<?php

declare(strict_types=1);

namespace App\Base\Report\Services;

use App\Base\Report\Dto\ReportFileDto;
use App\Base\Report\Exceptions\ReportFormatterNotConfiguredException;
use App\Base\Report\Formatters\ReportFormatterResolver;
use App\Exceptions\OperationException;
use App\Models\NotificationReport;
use Illuminate\Filesystem\FilesystemAdapter;

class ReportFileStorage
{
    /**
     * ReportFileStorage constructor.
     */
    public function __construct(
        private readonly ReportFormatterResolver $formatter_resolver,
        private readonly FilesystemAdapter $disk,
        private readonly string $directory,
    ) {
        //
    }

    /**
     * Записать отчёт атомарно: содержимое собирает стратегия формата,
     * запись во временный файл + rename на постоянный путь.
     *
     * @param  array<int, array{channel: string, total: int, failed: int}>  $rows
     * @return string постоянный путь файла
     *
     * @throws ReportFormatterNotConfiguredException
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
     * Файл готового отчёта для скачивания: абсолютный путь и имя.
     *
     * @throws OperationException если файл отсутствует на диске
     */
    public function fileForDownload(NotificationReport $report) : ReportFileDto
    {
        if ($report->file_path === null || ! $this->disk->exists($report->file_path)) {
            throw new OperationException('Файл отчёта недоступен.', 409);
        }

        $extension = pathinfo($report->file_path, PATHINFO_EXTENSION);

        return new ReportFileDto(
            absolute_path: $this->disk->path($report->file_path),
            download_name: "notification_report_{$report->id}.{$extension}",
        );
    }

    /**
     * Убрать недописанный временный файл упавшей генерации.
     *
     * @throws ReportFormatterNotConfiguredException
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
        return "{$this->directory}/tmp/report_{$report_id}.{$extension}.tmp";
    }

    /**
     * Постоянный путь файла отчёта.
     */
    private function finalPath(int $report_id, string $extension) : string
    {
        return "{$this->directory}/report_{$report_id}.{$extension}";
    }
}
