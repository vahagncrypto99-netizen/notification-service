<?php

declare(strict_types=1);

namespace App\Domains\Notification\Jobs;

use App\Domains\Notification\Channels\ChannelSenderResolver;
use App\Domains\Notification\Dto\ChannelMessageDto;
use App\Domains\Notification\Enum\NotificationStatusEnum;
use App\Domains\Notification\Events\NotificationFailed;
use App\Domains\Notification\Events\NotificationSent;
use App\Domains\Notification\Repository\NotificationRepository;
use App\Models\Notification;
use App\Queue\Queue;
use App\Queue\QueueSettings;
use App\Services\Delivery\PermanentDeliveryException;
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
     * Доставка уведомления. Транзиентный сбой уходит в ретрай,
     * неисправимый отказ гасит уведомление сразу.
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
            Log::error('Уведомление не найдено, отправка пропущена.', [
                'notification_id' => $this->notification_id,
            ]);

            return;
        }

        /**
         * Guard идемпотентности: уведомление уже в терминальном статусе —
         * повторная отправка невозможна (гонка watchdog против живой джобы).
         */
        if (! $notification->inStatus(NotificationStatusEnum::Processing)) {
            Log::info('Уведомление уже в терминальном статусе, отправка пропущена.', [
                'notification_id' => $notification->id,
                'status' => $notification->status->value,
            ]);

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
        } catch (PermanentDeliveryException $exception) {
            /**
             * Неисправимый отказ (невалидный адрес, блокировка бота):
             * ретраи бессмысленны, уведомление гасится сразу.
             */
            if ($repository->markAsFailed($notification->id, $exception->getMessage())) {
                NotificationFailed::dispatch($notification->refresh());
            }

            Log::error('Уведомление не может быть доставлено.', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'channel' => $notification->channel->value,
                'error' => $exception->getMessage(),
            ]);

            return;
        } catch (Throwable $exception) {
            $repository->rememberLastError($notification->id, $exception->getMessage());

            throw $exception;
        }

        if ($repository->markAsSent($notification->id)) {
            NotificationSent::dispatch($notification->refresh());
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

        Log::error('Уведомление не доставлено, попытки исчерпаны.', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'channel' => $notification->channel->value,
            'attempts' => $notification->attempts_count,
            'error' => $exception?->getMessage(),
        ]);
    }
}
