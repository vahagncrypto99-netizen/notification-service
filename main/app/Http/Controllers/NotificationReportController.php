<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Base\Notification\Dto\RequestReportDto;
use App\Base\Notification\Enum\ReportStatusEnum;
use App\Base\Notification\Manager;
use App\Http\Resources\NotificationReportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationReportController extends Controller
{
    /**
     * NotificationReportController constructor.
     */
    public function __construct(
        private readonly Manager $manager
    ) {
        //
    }

    /**
     * Запрос генерации отчёта за период.
     */
    public function store(Request $request) : JsonResponse
    {
        $report = $this->manager->requestReport(RequestReportDto::validateAndCreate($request->all()));

        return (new NotificationReportResource($report))->response()->setStatusCode(201);
    }

    /**
     * Статус готовности отчёта.
     */
    public function show(int $report_id) : NotificationReportResource
    {
        $report = $this->manager->findReport($report_id);

        abort_if($report === null, 404);

        return new NotificationReportResource($report);
    }

    /**
     * Скачивание готового отчёта.
     */
    public function download(int $report_id) : StreamedResponse
    {
        $report = $this->manager->findReport($report_id);

        abort_if($report === null, 404);

        abort_if(
            $report->status !== ReportStatusEnum::Done || $report->file_path === null,
            409,
            'Отчёт ещё не готов к скачиванию.'
        );

        $disk = (string) config('notification.reports.disk');

        abort_if(! Storage::disk($disk)->exists($report->file_path), 409, 'Файл отчёта недоступен.');

        $extension = pathinfo($report->file_path, PATHINFO_EXTENSION);

        return Storage::disk($disk)->download(
            $report->file_path,
            "notification_report_{$report->id}.{$extension}"
        );
    }
}
