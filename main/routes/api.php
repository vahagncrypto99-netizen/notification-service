<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationReportController;
use App\Http\Middlewares\ApiSignatureAuth;
use Illuminate\Support\Facades\Route;

/**
 * Аутентификация (ApiSignatureAuth) и rate limiting навешаны на всю
 * группу api в bootstrap/app.php — каждый маршрут защищён по умолчанию.
 */

/**
 * Readiness — для оркестратора/мониторинга: без подписи и лимита
 * (probe не умеет HMAC и не должен выедать rate limit).
 */
Route::get('/health', HealthController::class)->withoutMiddleware([
    'throttle:api',
    ApiSignatureAuth::class,
]);

Route::post('/notifications', [NotificationController::class, 'store']);
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/{notification_id}', [NotificationController::class, 'show'])->whereNumber('notification_id');

Route::post('/reports', [NotificationReportController::class, 'store']);
Route::get('/reports/{report_id}', [NotificationReportController::class, 'show'])->whereNumber('report_id');
Route::get('/reports/{report_id}/download', [NotificationReportController::class, 'download'])->whereNumber('report_id');
