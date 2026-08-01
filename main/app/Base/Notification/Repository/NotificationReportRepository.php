<?php

declare(strict_types=1);

namespace App\Base\Notification\Repository;

use App\Base\Notification\Enum\ReportStatusEnum;
use App\Models\NotificationReport;
use App\Repository\Base;

/**
 * @extends Base<NotificationReport>
 */
class NotificationReportRepository extends Base
{
    /**
     * Инициализация репозитория.
     *
     * @return class-string<NotificationReport>
     */
    protected function init() : string
    {
        return NotificationReport::class;
    }

    /**
     * Переход pending → processing.
     * Применяется только если отчёт всё ещё в pending.
     *
     * @return bool сработал ли переход
     */
    public function markAsProcessing(int $report_id) : bool
    {
        return (bool) $this->query()->whereKey($report_id)->where(
            'status',
            ReportStatusEnum::Pending
        )->update(['status' => ReportStatusEnum::Processing]);
    }

    /**
     * Переход processing → done с путём готового файла.
     *
     * @return bool сработал ли переход
     */
    public function markAsDone(int $report_id, string $file_path) : bool
    {
        return (bool) $this->query()->whereKey($report_id)->where(
            'status',
            ReportStatusEnum::Processing
        )->update([
            'status' => ReportStatusEnum::Done,
            'file_path' => $file_path,
        ]);
    }

    /**
     * Переход processing → failed с фиксацией ошибки.
     *
     * @return bool сработал ли переход
     */
    public function markAsFailed(int $report_id, ?string $error) : bool
    {
        return (bool) $this->query()->whereKey($report_id)->where(
            'status',
            ReportStatusEnum::Processing
        )->update([
            'status' => ReportStatusEnum::Failed,
            'error' => $error,
        ]);
    }
}
