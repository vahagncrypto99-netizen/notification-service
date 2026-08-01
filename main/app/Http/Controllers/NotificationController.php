<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Base\Notification\Dto\CreateNotificationDto;
use App\Base\Notification\Dto\NotificationHistoryDto;
use App\Base\Notification\Manager;
use App\Exceptions\OperationException;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotificationController extends Controller
{
    /**
     * NotificationController constructor.
     */
    public function __construct(
        private readonly Manager $manager
    ) {
        //
    }

    /**
     * Создание уведомления.
     */
    public function store(Request $request) : JsonResponse
    {
        try {
            $notification = $this->manager->create(
                CreateNotificationDto::validateAndCreate($request->all())
            );

            return ApiResponse::success('Уведомление создано.', 201, [
                'notification' => new NotificationResource($notification),
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
     * Статус уведомления.
     */
    public function show(int $notification_id) : JsonResponse
    {
        try {
            $notification = $this->manager->getNotification($notification_id);

            return ApiResponse::success('Уведомление получено.', 200, [
                'notification' => new NotificationResource($notification),
            ]);
        } catch (OperationException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Внутренняя ошибка сервиса.', 500);
        }
    }

    /**
     * История уведомлений пользователя с фильтрами по статусу и каналу.
     */
    public function index(Request $request) : JsonResponse
    {
        try {
            $history = $this->manager->historyForUser(
                NotificationHistoryDto::validateAndCreate($request->query())
            );

            return ApiResponse::success(
                'История уведомлений.',
                200,
                NotificationResource::paginateCollection($history)
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error('Ошибка валидации.', 422, ['errors' => $exception->errors()]);
        } catch (OperationException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Внутренняя ошибка сервиса.', 500);
        }
    }
}
