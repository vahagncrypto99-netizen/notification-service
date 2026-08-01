<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Models\NotificationMessengerQueue;
use App\Services\Delivery\DeliveryResultHandlerInterface;
use App\Services\Delivery\Messenger\Dto\SenderDto;
use App\Services\Delivery\Messenger\Repository\MessengerQueueRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class Schedule
{
    /**
     * Максимум попыток отправки одного сообщения кроном.
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * Schedule constructor.
     */
    public function __construct(
        protected MessengerQueueRepository $queue,
        protected MessengerSenderInterface $sender,
        protected MessageFormatter $message_formatter,
        protected DeliveryResultHandlerInterface $delivery_result_handler,
        protected string $messenger,
    ) {
        //
    }

    /**
     * Отправка сообщений по расписанию.
     *
     * Запись покидает очередь только после исхода отправки — упавший
     * между выборкой и отправкой процесс не теряет сообщения (повторная
     * отправка возможна: канал работает как at-least-once).
     * От параллельной выборки пачки защищает withoutOverlapping
     * задачи планировщика.
     */
    public function send() : void
    {
        $records = $this->queue->getNextSendingPart($this->messenger);

        /** @var NotificationMessengerQueue $record */
        foreach ($records as $record) {
            try {
                $data = SenderDto::from([
                    'chat_id' => $record->messenger_id,
                    'message' => $this->message_formatter->prepareMessage($record->message),
                ]);

                $response = $this->sender->send($data);

                if ($response->success) {
                    $this->confirm($record);
                } elseif ($response->should_unsubscribe) {
                    /**
                     * Получатель заблокировал бота — в боевой
                     * реализации здесь отписка пользователя.
                     */
                    $this->reject($record, "Получатель {$record->messenger_id} недоступен.");
                } elseif ($response->should_retry) {
                    $this->retryOrReject($record, (string) $response->message);
                } else {
                    $this->reject($record, (string) $response->message);
                }
            } catch (Throwable $exception) {
                report($exception);

                Log::error(
                    "Ошибка при отправке сообщения в мессенджер. Chat ID: {$record->messenger_id}.",
                    ['error' => $exception->getMessage()]
                );

                $this->retryOrReject($record, $exception->getMessage());
            }
        }
    }

    // ****************************************************************
    // *************************** Support ****************************
    // ****************************************************************

    /**
     * Сообщение отправлено: удаление из очереди и подтверждение доставки.
     */
    protected function confirm(NotificationMessengerQueue $record) : void
    {
        $this->queue->deleteByIds([$record->id]);

        if ($record->notification_id !== null) {
            $this->delivery_result_handler->sent($record->notification_id);
        }
    }

    /**
     * Терминальный отказ: удаление из очереди и фиксация сбоя доставки.
     */
    protected function reject(NotificationMessengerQueue $record, string $error) : void
    {
        Log::warning("[{$this->messenger}] Сообщение для {$record->messenger_id} не будет отправлено: {$error}");

        $this->queue->deleteByIds([$record->id]);

        if ($record->notification_id !== null) {
            $this->delivery_result_handler->failed($record->notification_id, $error);
        }
    }

    /**
     * Повтор следующим запуском; после MAX_ATTEMPTS — терминальный отказ.
     */
    protected function retryOrReject(NotificationMessengerQueue $record, string $error) : void
    {
        if ($record->attempts + 1 >= self::MAX_ATTEMPTS) {
            $this->reject($record, $error);

            return;
        }

        $this->queue->registerAttempt($record->id);
    }
}
