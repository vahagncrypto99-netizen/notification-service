<?php

declare(strict_types=1);

namespace App\Base\Notification\Jobs;

use App\Base\Notification\Channels\ChannelSenderResolver;
use App\Base\Notification\Dto\ChannelMessageDto;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Events\NotificationFailed;
use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;
use App\Queue\Queue;
use App\Queue\QueueSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob extends Queue
{
    /**
     * Количество попыток отправки.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Таймаут выполнения.
     *
     * @var int
     */
    public $timeout = QueueSettings::HIGH_PRIORITY_TIMEOUT;

    /**
     * SendNotificationJob constructor.
     */
    public function __construct(
        private readonly int $notification_id
    ) {
        $this->onQueue(QueueSettings::HIGH_PRIORITY_QUEUE);
    }

    /**
     * Экспоненциальные задержки между попытками (секунды).
     *
     * @return array<int, int>
     */
    public function backoff() : array
    {
        return [10, 30, 60, 120];
    }

    /**
     * Передача уведомления каналу доставки.
     *
     * Уведомление остаётся в processing: статус sent/failed выставит
     * контур канала после фактической отправки (DeliveryResultHandler).
     *
     * @throws Throwable
     */
    public function handle(
        NotificationRepository $repository,
        ChannelSenderResolver $resolver
    ) : void {
        /** @var Notification|null $notification */
        $notification = $repository->find($this->notification_id);

        if ($notification === null) {
            Log::error("Уведомление #{$this->notification_id} не найдено, отправка пропущена.");

            return;
        }

        /**
         * Guard идемпотентности: уведомление уже в терминальном статусе —
         * повторная отправка невозможна (гонка watchdog против живой джобы).
         */
        if ($notification->status !== NotificationStatusEnum::Processing) {
            Log::info(
                "Уведомление #{$notification->id} уже в статусе {$notification->status->value}, отправка пропущена."
            );

            return;
        }

        $notification->registerAttempt();

        $message = new ChannelMessageDto(
            notification_id: $notification->id,
            user_id: $notification->user_id,
            message: $notification->message,
        );

        try {
            $resolver->resolve($notification->channel)->send($message);
        } catch (Throwable $exception) {
            $notification->last_error = $exception->getMessage();

            $notification->save();

            throw $exception;
        }
    }

    /**
     * Терминальный сбой: все попытки исчерпаны.
     */
    public function failed(?Throwable $exception) : void
    {
        /** @var NotificationRepository $repository */
        $repository = app(NotificationRepository::class);

        /** @var Notification|null $notification */
        $notification = $repository->find($this->notification_id);

        if ($notification === null) {
            return;
        }

        if ($repository->markAsFailed($notification->id, $exception?->getMessage())) {
            NotificationFailed::dispatch($notification->refresh());
        }

        Log::error(
            "Уведомление #{$this->notification_id} не доставлено, попытки исчерпаны.",
            ['error' => $exception?->getMessage()]
        );
    }
}
