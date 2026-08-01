<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Base\Notification\Dto\RequestReportDto;
use App\Base\Notification\Manager;
use App\Exceptions\OperationException;
use App\Http\Resources\NotificationReportResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        try {
            $report = $this->manager->requestReport(
                RequestReportDto::validateAndCreate($request->all())
            );

            return ApiResponse::success('Генерация отчёта запрошена.', 201, [
                'report' => new NotificationReportResource($report),
            ]);
        } catch (ValidationException $exception) {
            return ApiResponse::error('Ошибка валидации.', 422, ['errors' => $exception->errors()]);
        } catch (OperationException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Внутренняя ошибка сервиса.', 500);
        }
    }

    /**
     * Статус готовности отчёта.
     */
    public function show(int $report_id) : JsonResponse
    {
        try {
            $report = $this->manager->getReport($report_id);

            return ApiResponse::success('Отчёт получен.', 200, [
                'report' => new NotificationReportResource($report),
            ]);
        } catch (OperationException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Внутренняя ошибка сервиса.', 500);
        }
    }

    /**
     * Скачивание готового отчёта.
     */
    public function download(int $report_id) : Response
    {
        try {
            return $this->manager->downloadReport($report_id);
        } catch (OperationException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Внутренняя ошибка сервиса.', 500);
        }
    }
}
