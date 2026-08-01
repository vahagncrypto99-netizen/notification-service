<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Services\HealthChecker;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Readiness-проверка: сервис и его зависимости (БД, брокер).
     * Liveness («процесс жив») — штатный /up.
     */
    public function __invoke(HealthChecker $health_checker) : JsonResponse
    {
        $checks = $health_checker->checks();

        if (! $health_checker->healthy($checks)) {
            return ApiResponse::error('Сервис нездоров.', 503, ['checks' => $checks]);
        }

        return ApiResponse::success('Сервис здоров.', 200, ['checks' => $checks]);
    }
}
