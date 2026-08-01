<?php

declare(strict_types=1);

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationReportController;
use Illuminate\Support\Facades\Route;

/**
 * Аутентификация (ApiTokenAuth) навешана на всю группу api
 * в bootstrap/app.php — каждый маршрут здесь защищён по умолчанию.
 */
Route::post('/notifications', [NotificationController::class, 'store']);
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/{notification_id}', [NotificationController::class, 'show'])->whereNumber('notification_id');

Route::post('/reports', [NotificationReportController::class, 'store']);
Route::get('/reports/{report_id}', [NotificationReportController::class, 'show'])->whereNumber('report_id');
Route::get('/reports/{report_id}/download', [NotificationReportController::class, 'download'])->whereNumber('report_id');
