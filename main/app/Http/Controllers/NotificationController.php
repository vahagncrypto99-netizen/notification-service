<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Base\Notification\Dto\CreateNotificationDto;
use App\Base\Notification\Dto\NotificationHistoryDto;
use App\Base\Notification\Manager;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
        $notification = $this->manager->create(CreateNotificationDto::validateAndCreate($request->all()));

        return (new NotificationResource($notification))->response()->setStatusCode(201);
    }

    /**
     * Статус уведомления.
     */
    public function show(int $notification_id) : NotificationResource
    {
        $notification = $this->manager->find($notification_id);

        abort_if($notification === null, 404);

        return new NotificationResource($notification);
    }

    /**
     * История уведомлений пользователя с фильтрами по статусу и каналу.
     */
    public function index(Request $request) : AnonymousResourceCollection
    {
        $history = $this->manager->historyForUser(NotificationHistoryDto::validateAndCreate($request->query()));

        return NotificationResource::collection($history);
    }
}
