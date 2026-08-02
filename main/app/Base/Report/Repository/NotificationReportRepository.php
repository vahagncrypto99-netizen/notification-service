<?php

declare(strict_types=1);

namespace App\Base\Report\Repository;

use App\Base\Report\Enum\ReportStatusEnum;
use App\Models\NotificationReport;
use App\Repository\Base;
use Carbon\Carbon;
use Closure;
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
     * Обход зависших отчётов пачками: pending — потерянный dispatch,
     * processing — убитый воркер. chunkById пагинирует по id —
     * сдвиг updated_at внутри обработки не ломает выборку.
     *
     * @param  Closure(Collection<int, NotificationReport>): void  $callback
     */
    public function chunkStuck(Carbon $threshold, int $chunk_size, Closure $callback) : void
    {
        $this->query()
            ->select(['id', 'updated_at'])
            ->whereIn('status', [ReportStatusEnum::Pending, ReportStatusEnum::Processing])
            ->where('updated_at', '<', $threshold)
            ->chunkById($chunk_size, $callback);
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
