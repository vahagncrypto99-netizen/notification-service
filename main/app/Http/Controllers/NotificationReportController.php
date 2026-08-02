<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Report\Dto\RequestReportDto;
use App\Domains\Report\ReportManager;
use App\Exceptions\OperationException;
use App\Http\Resources\NotificationReportResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class NotificationReportController extends Controller
{
    /**
     * NotificationReportController constructor.
     */
    public function __construct(
        private readonly ReportManager $manager
    ) {
        //
    }

    /**
     * Запрос генерации отчёта за период.
     */
    #[OA\Post(
        path: '/reports', description: 'Создаёт отчёт в статусе pending; генерация выполняется асинхронно.', summary: 'Запросить генерацию отчёта', security: [['bearer_token' => [], 'request_timestamp' => [], 'request_nonce' => [], 'request_signature' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['user_id', 'period_from', 'period_to'],
            properties: [
                new OA\Property(
                    property: 'user_id',
                    type: 'integer',
                    example: 42,
                    minimum: 1
                ),
                new OA\Property(property: 'period_from', type: 'string', format: 'date', example: '2026-07-01'),
                new OA\Property(property: 'period_to', type: 'string', format: 'date', example: '2026-08-01'),
            ]
        )), tags: ['Отчёты'], responses: [
            new OA\Response(response: 201, description: 'Генерация запрошена', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Генерация отчёта запрошена.'),
                new OA\Property(property: 'payload', properties: [
                    new OA\Property(property: 'report', ref: '#/components/schemas/Report'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Не аутентифицирован', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
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
    #[OA\Get(
        path: '/reports/{report_id}',
        summary: 'Статус готовности отчёта',
        security: [['bearer_token' => [], 'request_timestamp' => [], 'request_nonce' => [], 'request_signature' => []]],
        tags: ['Отчёты'],
        parameters: [new OA\Parameter(name: 'report_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Отчёт', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Отчёт получен.'),
                new OA\Property(property: 'payload', properties: [
                    new OA\Property(property: 'report', ref: '#/components/schemas/Report'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'Не найден', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 401, description: 'Не аутентифицирован', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
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
    #[OA\Get(
        path: '/reports/{report_id}/download', description: 'Отдаёт файл отчёта; до готовности отвечает 409.', summary: 'Скачать готовый отчёт', security: [['bearer_token' => [], 'request_timestamp' => [], 'request_nonce' => [], 'request_signature' => []]], tags: ['Отчёты'], parameters: [new OA\Parameter(name: 'report_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [
            new OA\Response(response: 200, description: 'CSV-файл отчёта', content: new OA\MediaType(mediaType: 'text/csv')),
            new OA\Response(response: 409, description: 'Отчёт не готов', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 404, description: 'Не найден', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 401, description: 'Не аутентифицирован', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function download(int $report_id) : Response
    {
        try {
            $file = $this->manager->downloadReport($report_id);

            return response()->download($file->absolute_path, $file->download_name);
        } catch (OperationException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Внутренняя ошибка сервиса.', 500);
        }
    }
}
