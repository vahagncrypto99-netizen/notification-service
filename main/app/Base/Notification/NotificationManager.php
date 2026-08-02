<?php

declare(strict_types=1);

namespace App\Base\Notification;

use App\Base\Concerns\FailsOperations;
use App\Base\Notification\Actions\CreateNotification;
use App\Base\Notification\Dto\CreateNotificationDto;
use App\Base\Notification\Dto\NotificationHistoryDto;
use App\Base\Notification\Jobs\SendNotificationJob;
use App\Base\Notification\Repository\NotificationRepository;
use App\Exceptions\OperationException;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Throwable;

class NotificationManager
{
    use FailsOperations;

    /**
     * NotificationManager constructor.
     */
    public function __construct(
        private readonly CreateNotification $create_notification,
        private readonly NotificationRepository $repository,
    ) {
        //
    }

    /**
     * Создание уведомления с постановкой отправки в очередь.
     *
     * @throws OperationException
     */
    public function create(CreateNotificationDto $dto) : Notification
    {
        try {
            $notification = $this->create_notification->handle($dto);

            SendNotificationJob::dispatch($notification->id);

            return $notification;
        } catch (Throwable $exception) {
            $this->fail('Не удалось создать уведомление.', $exception, [
                'user_id' => $dto->user_id,
                'channel' => $dto->channel->value,
            ]);
        }
    }

    /**
     * Уведомление по идентификатору.
     *
     * @throws OperationException
     */
    public function getNotification(int $notification_id) : Notification
    {
        try {
            /** @var Notification|null $notification */
            $notification = $this->repository->find($notification_id);

            if ($notification === null) {
                throw new OperationException('Уведомление не найдено.', 404);
            }

            return $notification;
        } catch (OperationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->fail('Не удалось получить уведомление.', $exception, [
                'notification_id' => $notification_id,
            ]);
        }
    }

    /**
     * История уведомлений пользователя с фильтрами.
     *
     * @return LengthAwarePaginator<int, Notification>
     *
     * @throws OperationException
     */
    public function historyForUser(NotificationHistoryDto $dto) : LengthAwarePaginator
    {
        try {
            return $this->repository->historyForUser(
                $dto->user_id,
                $dto->status,
                $dto->channel
            );
        } catch (Throwable $exception) {
            $this->fail('Не удалось получить историю уведомлений.', $exception, [
                'user_id' => $dto->user_id,
                'status' => $dto->status?->value,
                'channel' => $dto->channel?->value,
            ]);
        }
    }
}
