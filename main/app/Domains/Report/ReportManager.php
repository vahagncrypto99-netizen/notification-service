<?php

declare(strict_types=1);

namespace App\Domains\Report;

use App\Concerns\FailsOperations;
use App\Domains\Report\Actions\RequestReport;
use App\Domains\Report\Dto\ReportFileDto;
use App\Domains\Report\Dto\RequestReportDto;
use App\Domains\Report\Enum\ReportStatusEnum;
use App\Domains\Report\Jobs\GenerateReportJob;
use App\Domains\Report\Repository\NotificationReportRepository;
use App\Domains\Report\Services\ReportFileStorage;
use App\Exceptions\OperationException;
use App\Models\NotificationReport;
use Throwable;

class ReportManager
{
    use FailsOperations;

    /**
     * ReportManager constructor.
     */
    public function __construct(
        private readonly RequestReport $request_report,
        private readonly NotificationReportRepository $repository,
        private readonly ReportFileStorage $file_storage,
    ) {
        //
    }

    /**
     * Запрос генерации отчёта за период.
     *
     * @throws OperationException
     */
    public function requestReport(RequestReportDto $dto) : NotificationReport
    {
        try {
            $report = $this->request_report->handle($dto);

            GenerateReportJob::dispatch($report->id);

            return $report;
        } catch (Throwable $exception) {
            $this->fail('Не удалось запросить генерацию отчёта.', $exception, [
                'user_id' => $dto->user_id,
                'period_from' => $dto->period_from->toDateString(),
                'period_to' => $dto->period_to->toDateString(),
            ]);
        }
    }

    /**
     * Отчёт по идентификатору.
     *
     * @throws OperationException
     */
    public function getReport(int $report_id) : NotificationReport
    {
        try {
            /** @var NotificationReport|null $report */
            $report = $this->repository->find($report_id);

            if ($report === null) {
                throw new OperationException('Отчёт не найден.', 404);
            }

            return $report;
        } catch (OperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->fail('Не удалось получить отчёт.', $exception, [
                'report_id' => $report_id,
            ]);
        }
    }

    /**
     * Файл готового отчёта для скачивания.
     *
     * @throws OperationException
     */
    public function downloadReport(int $report_id) : ReportFileDto
    {
        try {
            $report = $this->getReport($report_id);

            if (! $report->inStatus(ReportStatusEnum::Done) || $report->file_path === null) {
                throw new OperationException('Отчёт ещё не готов к скачиванию.', 409);
            }

            return $this->file_storage->fileForDownload($report);
        } catch (OperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->fail('Не удалось скачать отчёт.', $exception, [
                'report_id' => $report_id,
            ]);
        }
    }
}
