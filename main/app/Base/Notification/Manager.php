<?php

declare(strict_types=1);

namespace App\Base\Notification;

use App\Base\Notification\Actions\CreateNotification;
use App\Base\Notification\Actions\RequestReport;
use App\Base\Notification\Dto\CreateNotificationDto;
use App\Base\Notification\Dto\NotificationHistoryDto;
use App\Base\Notification\Dto\RequestReportDto;
use App\Base\Notification\Jobs\GenerateReportJob;
use App\Base\Notification\Jobs\SendNotificationJob;
use App\Base\Notification\Repository\NotificationReportRepository;
use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;
use App\Models\NotificationReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class Manager
{
    /**
     * Manager constructor.
     */
    public function __construct(
        private readonly CreateNotification $create_notification,
        private readonly RequestReport $request_report,
        private readonly NotificationRepository $repository,
        private readonly NotificationReportRepository $report_repository
    ) {
        //
    }

    /**
     * Создание уведомления с постановкой отправки в очередь.
     */
    public function create(CreateNotificationDto $dto) : Notification
    {
        $notification = $this->create_notification->handle($dto);

        SendNotificationJob::dispatch($notification->id);

        return $notification;
    }

    /**
     * Поиск уведомления по идентификатору.
     */
    public function find(int $notification_id) : ?Notification
    {
        /** @var Notification|null */
        return $this->repository->find($notification_id);
    }

    /**
     * История уведомлений пользователя с фильтрами.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    public function historyForUser(NotificationHistoryDto $dto) : LengthAwarePaginator
    {
        return $this->repository->historyForUser(
            $dto->user_id,
            $dto->status,
            $dto->channel
        );
    }

    /**
     * Запрос генерации отчёта за период.
     */
    public function requestReport(RequestReportDto $dto) : NotificationReport
    {
        $report = $this->request_report->handle($dto);

        GenerateReportJob::dispatch($report->id);

        return $report;
    }

    /**
     * Поиск отчёта по идентификатору.
     */
    public function findReport(int $report_id) : ?NotificationReport
    {
        /** @var NotificationReport|null */
        return $this->report_repository->find($report_id);
    }
}
