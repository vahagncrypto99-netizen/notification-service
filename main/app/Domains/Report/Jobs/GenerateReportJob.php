<?php

declare(strict_types=1);

namespace App\Domains\Report\Jobs;

use App\Domains\Notification\Repository\NotificationRepository;
use App\Domains\Report\Enum\ReportStatusEnum;
use App\Domains\Report\Repository\NotificationReportRepository;
use App\Domains\Report\Services\ReportFileStorage;
use App\Models\NotificationReport;
use App\Queue\Queue;
use App\Queue\QueueSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateReportJob extends Queue
{
    /**
     * Количество попыток генерации.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Таймаут выполнения.
     *
     * @var int
     */
    public $timeout = QueueSettings::LOW_PRIORITY_TIMEOUT;

    /**
     * GenerateReportJob constructor.
     */
    public function __construct(
        private readonly int $report_id
    ) {
        $this->onQueue(QueueSettings::LOW_PRIORITY_QUEUE);
    }

    /**
     * Задержки между попытками (секунды).
     *
     * @return array<int, int>
     */
    public function backoff() : array
    {
        return [30, 120];
    }

    /**
     * Генерация отчёта. Статус done ставится только после атомарного
     * переноса файла на постоянный путь.
     *
     * @throws Throwable
     */
    public function handle(
        NotificationReportRepository $report_repository,
        NotificationRepository $notification_repository,
        ReportFileStorage $file_storage
    ) : void {
        /** @var NotificationReport|null $report */
        $report = $report_repository->find($this->report_id);

        if ($report === null) {
            Log::error('Отчёт не найден, генерация пропущена.', [
                'report_id' => $this->report_id,
            ]);

            return;
        }

        /**
         * Guard: генерация стартует из pending — повторная джоба по уже
         * готовому или упавшему отчёту ничего не делает. Ретрай этой же
         * джобы (attempts > 1) продолжает из processing, который сам
         * и оставил на упавшей попытке.
         */
        $started = $report_repository->markAsProcessing($report->id);

        $retrying = $this->attempts() > 1
            && $report->refresh()->inStatus(ReportStatusEnum::Processing);

        if (! $started && ! $retrying) {
            Log::info('Отчёт уже в терминальном статусе, генерация пропущена.', [
                'report_id' => $report->id,
                'status' => $report->refresh()->status->value,
            ]);

            return;
        }

        /**
         * Ретрай сбрасывает часы зависания: каждая живая попытка двигает
         * updated_at, и watchdog не передиспатчит работающую генерацию.
         */
        if ($retrying) {
            $report->touch();
        }

        $rows = $notification_repository->aggregateByChannel(
            $report->user_id,
            $report->period_from,
            $report->period_to
        );

        $file_path = $file_storage->write($report->id, $rows);

        $report_repository->markAsDone($report->id, $file_path);

        Log::info('Отчёт сгенерирован.', [
            'report_id' => $report->id,
            'user_id' => $report->user_id,
            'file_path' => $file_path,
        ]);
    }

    /**
     * Терминальный сбой генерации.
     */
    public function failed(?Throwable $exception) : void
    {
        /** @var NotificationReportRepository $repository */
        $repository = app(NotificationReportRepository::class);

        $repository->markAsFailed($this->report_id, $exception?->getMessage());

        /**
         * Недописанный временный файл убираем — при повторном запросе
         * отчёта генерация начнётся с чистого листа.
         */
        app(ReportFileStorage::class)->deleteTemp($this->report_id);

        Log::error('Генерация отчёта упала.', [
            'report_id' => $this->report_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
