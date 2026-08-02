<?php

declare(strict_types=1);

namespace App\Base\Report\Actions;

use App\Base\Report\Dto\RequestReportDto;
use App\Base\Report\Enum\ReportStatusEnum;
use App\Base\Report\Repository\NotificationReportRepository;
use App\Models\NotificationReport;

class RequestReport
{
    /**
     * RequestReport constructor.
     */
    public function __construct(
        private readonly NotificationReportRepository $repository
    ) {
        //
    }

    /**
     * Создать отчёт в статусе pending.
     */
    public function handle(RequestReportDto $dto) : NotificationReport
    {
        /** @var NotificationReport $report */
        $report = $this->repository->new();

        $report->user_id = $dto->user_id;
        $report->period_from = $dto->period_from;
        $report->period_to = $dto->period_to;
        $report->status = ReportStatusEnum::Pending;

        $report->save();

        return $report;
    }
}
