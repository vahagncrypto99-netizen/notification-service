<?php

declare(strict_types=1);

namespace App\Base\Notification\Actions;

use App\Base\Notification\Dto\CreateNotificationDto;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;

class CreateNotification
{
    /**
     * CreateNotification constructor.
     */
    public function __construct(
        private readonly NotificationRepository $repository
    ) {
        //
    }

    /**
     * Создать уведомление в статусе «в обработке».
     */
    public function handle(CreateNotificationDto $dto) : Notification
    {
        /** @var Notification $notification */
        $notification = $this->repository->new();

        $notification->message = $dto->message;
        $notification->status = NotificationStatusEnum::Processing;
        $notification->user_id = $dto->user_id;
        $notification->channel = $dto->channel;

        $notification->save();

        return $notification;
    }
}
