<?php

declare(strict_types=1);

namespace App\Base\Notification;

use App\Base\Notification\Actions\RequestReport;
use App\Base\Notification\Concerns\FailsOperations;
use App\Base\Notification\Dto\ReportFileDto;
use App\Base\Notification\Dto\RequestReportDto;
use App\Base\Notification\Enum\ReportStatusEnum;
use App\Base\Notification\Jobs\GenerateReportJob;
use App\Base\Notification\Repository\NotificationReportRepository;
use App\Base\Notification\Services\ReportFileStorage;
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
            $this->fail('Не удалось запросить генерацию отчёта.', $exception);
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
            $this->fail('Не удалось получить отчёт.', $exception);
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

            if ($report->status !== ReportStatusEnum::Done || $report->file_path === null) {
                throw new OperationException('Отчёт ещё не готов к скачиванию.', 409);
            }

            return $this->file_storage->fileForDownload($report);
        } catch (OperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->fail('Не удалось скачать отчёт.', $exception);
        }
    }
}
