<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Events\NotificationFailed;
use App\Base\Notification\Events\NotificationSent;
use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;
use App\Services\Delivery\DeliveryResultHandlerInterface;

class DeliveryResultHandler implements DeliveryResultHandlerInterface
{
    /**
     * DeliveryResultHandler constructor.
     */
    public function __construct(
        private readonly NotificationRepository $repository,
    ) {
        //
    }

    /**
     * Подтверждение доставки: канал фактически отправил сообщение.
     */
    public function sent(int $notification_id) : void
    {
        /** @var Notification|null $notification */
        $notification = $this->repository->find($notification_id);

        if ($notification === null) {
            return;
        }

        if ($this->repository->markAsSent($notification_id)) {
            NotificationSent::dispatch($notification->refresh());
        }
    }

    /**
     * Терминальный отказ канала: попытки отправки исчерпаны
     * или отправка невозможна.
     */
    public function failed(int $notification_id, string $error) : void
    {
        /** @var Notification|null $notification */
        $notification = $this->repository->find($notification_id);

        if ($notification === null) {
            return;
        }

        if ($this->repository->markAsFailed($notification_id, $error)) {
            NotificationFailed::dispatch($notification->refresh());
        }
    }
}
