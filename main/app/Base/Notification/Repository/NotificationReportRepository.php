<?php

declare(strict_types=1);

namespace App\Base\Notification\Repository;

use App\Base\Notification\Enum\ReportStatusEnum;
use App\Models\NotificationReport;
use App\Repository\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Отчёты, зависшие в генерации дольше порога: pending — потерянный
     * dispatch, processing — убитый воркер.
     *
     * @return Collection<int, NotificationReport>
     */
    public function getStuck(Carbon $threshold, int $limit) : Collection
    {
        return $this->query()
            ->whereIn('status', [ReportStatusEnum::Pending, ReportStatusEnum::Processing])
            ->where('updated_at', '<', $threshold)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get(['id', 'updated_at']);
    }

    /**
     * Возврат processing → pending для передиспатча зависших генераций
     * одним условным UPDATE. Отчёты в других статусах не затрагиваются.
     *
     * @param  array<int, int>  $ids
     */
    public function resetToPendingAll(array $ids) : void
    {
        if ($ids === []) {
            return;
        }

        $this->query()->whereKey($ids)->where(
            'status',
            ReportStatusEnum::Processing
        )->update(['status' => ReportStatusEnum::Pending]);
    }
}
