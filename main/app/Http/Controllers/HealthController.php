<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\HealthChecker;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    /**
     * Readiness-проверка: сервис и его зависимости (БД, брокер).
     * Liveness («процесс жив») — штатный /up.
     */
    #[OA\Get(
        path: '/health',
        summary: 'Readiness-проверка сервиса',
        description: 'Состояние зависимостей (БД, брокер, кэш). Без аутентификации — для оркестратора/мониторинга.',
        tags: ['Служебное'],
        responses: [
            new OA\Response(response: 200, description: 'Сервис здоров', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Сервис здоров.'),
                new OA\Property(property: 'payload', properties: [
                    new OA\Property(property: 'checks', properties: [
                        new OA\Property(property: 'database', type: 'boolean', example: true),
                        new OA\Property(property: 'queue', type: 'boolean', example: true),
                        new OA\Property(property: 'cache', type: 'boolean', example: true),
                    ], type: 'object'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 503, description: 'Зависимость нездорова', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function __invoke(HealthChecker $health_checker) : JsonResponse
    {
        $checks = $health_checker->checks();

        if (! $health_checker->healthy($checks)) {
            return ApiResponse::error('Сервис нездоров.', 503, ['checks' => $checks]);
        }

        return ApiResponse::success('Сервис здоров.', 200, ['checks' => $checks]);
    }
}
