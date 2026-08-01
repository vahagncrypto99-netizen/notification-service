<?php

declare(strict_types=1);

namespace App\Base\Notification;

use App\Base\Notification\Actions\CreateNotification;
use App\Base\Notification\Actions\RequestReport;
use App\Base\Notification\Dto\CreateNotificationDto;
use App\Base\Notification\Dto\NotificationHistoryDto;
use App\Base\Notification\Dto\RequestReportDto;
use App\Base\Notification\Enum\ReportStatusEnum;
use App\Base\Notification\Jobs\GenerateReportJob;
use App\Base\Notification\Jobs\SendNotificationJob;
use App\Base\Notification\Repository\NotificationReportRepository;
use App\Base\Notification\Repository\NotificationRepository;
use App\Base\Notification\Services\ReportFileStorage;
use App\Exceptions\OperationException;
use App\Models\Notification;
use App\Models\NotificationReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Manager
{
    /**
     * Manager constructor.
     */
    public function __construct(
        private readonly CreateNotification $create_notification,
        private readonly RequestReport $request_report,
        private readonly NotificationRepository $repository,
        private readonly NotificationReportRepository $report_repository,
        private readonly ReportFileStorage $report_file_storage,
    ) {
        //
    }

    /**
     * Создание уведомления с постановкой отправки в очередь.
     *
     * @throws OperationException
     */
    public function create(CreateNotificationDto $dto) : Notification
    {
        try {
            $notification = $this->create_notification->handle($dto);

            SendNotificationJob::dispatch($notification->id);

            return $notification;
        } catch (Throwable $exception) {
            $this->fail('Не удалось создать уведомление.', $exception);
        }
    }

    /**
     * Уведомление по идентификатору.
     *
     * @throws OperationException
     */
    public function getNotification(int $notification_id) : Notification
    {
        try {
            /** @var Notification|null $notification */
            $notification = $this->repository->find($notification_id);

            if ($notification === null) {
                throw new OperationException('Уведомление не найдено.', 404);
            }

            return $notification;
        } catch (OperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->fail('Не удалось получить уведомление.', $exception);
        }
    }

    /**
     * История уведомлений пользователя с фильтрами.
     *
     * @return LengthAwarePaginator<int, Notification>
     *
     * @throws OperationException
     */
    public function historyForUser(NotificationHistoryDto $dto) : LengthAwarePaginator
    {
        try {
            return $this->repository->historyForUser(
                $dto->user_id,
                $dto->status,
                $dto->channel
            );
        } catch (Throwable $exception) {
            $this->fail('Не удалось получить историю уведомлений.', $exception);
        }
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
            $report = $this->report_repository->find($report_id);

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
     * Скачивание готового отчёта.
     *
     * @throws OperationException
     */
    public function downloadReport(int $report_id) : StreamedResponse
    {
        try {
            $report = $this->getReport($report_id);

            if ($report->status !== ReportStatusEnum::Done || $report->file_path === null) {
                throw new OperationException('Отчёт ещё не готов к скачиванию.', 409);
            }

            return $this->report_file_storage->download($report);
        } catch (OperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->fail('Не удалось скачать отчёт.', $exception);
        }
    }

    /**
     * Неожиданный сбой операции: лог + Sentry, наружу — OperationException.
     *
     * @throws OperationException
     */
    private function fail(string $message, Throwable $exception) : never
    {
        Log::error($message, ['error' => $exception->getMessage()]);

        report($exception);

        throw new OperationException($message, 500, $exception);
    }
}
