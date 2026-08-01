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
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/notifications', description: 'Создаёт уведомление в статусе processing и ставит доставку в очередь.', summary: 'Создать уведомление', security: [['bearer_token' => [], 'request_timestamp' => [], 'request_signature' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['message', 'user_id', 'channel'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Ваш заказ подтверждён',
                    maxLength: 500
                ),
                new OA\Property(
                    property: 'user_id',
                    type: 'integer',
                    example: 42,
                    minimum: 1
                ),
                new OA\Property(
                    property: 'channel',
                    type: 'string',
                    example: 'email',
                    enum: ['email', 'telegram']
                ),
            ]
        )), tags: ['Уведомления'], responses: [
            new OA\Response(response: 201, description: 'Уведомление создано', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Уведомление создано.'),
                new OA\Property(property: 'payload', properties: [
                    new OA\Property(property: 'notification', ref: '#/components/schemas/Notification'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Не аутентифицирован', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
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
    #[OA\Get(
        path: '/notifications/{notification_id}',
        summary: 'Статус уведомления',
        security: [['bearer_token' => [], 'request_timestamp' => [], 'request_signature' => []]],
        tags: ['Уведомления'],
        parameters: [new OA\Parameter(name: 'notification_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Уведомление', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Уведомление получено.'),
                new OA\Property(property: 'payload', properties: [
                    new OA\Property(property: 'notification', ref: '#/components/schemas/Notification'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'Не найдено', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 401, description: 'Не аутентифицирован', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
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
    #[OA\Get(
        path: '/notifications',
        summary: 'История уведомлений пользователя',
        security: [['bearer_token' => [], 'request_timestamp' => [], 'request_signature' => []]],
        tags: ['Уведомления'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['processing', 'sent', 'failed'])),
            new OA\Parameter(name: 'channel', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['email', 'telegram'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'История с пагинацией', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'История уведомлений.'),
                new OA\Property(property: 'payload', properties: [
                    new OA\Property(property: 'notifications', type: 'array', items: new OA\Items(ref: '#/components/schemas/Notification')),
                    new OA\Property(property: 'pagination', ref: '#/components/schemas/Pagination'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 422, description: 'Ошибка валидации', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 401, description: 'Не аутентифицирован', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
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
