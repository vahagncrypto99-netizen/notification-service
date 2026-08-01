<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Models\NotificationMessengerQueue;
use App\Services\Delivery\Messenger\Dto\SenderDto;
use App\Services\Delivery\Messenger\Repository\MessengerQueueRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class Schedule
{
    /**
     * Schedule constructor.
     */
    public function __construct(
        protected MessengerQueueRepository $queue,
        protected MessengerSenderInterface $sender,
        protected MessageFormatter $message_formatter,
        protected string $messenger,
    ) {
        //
    }

    /**
     * Отправка сообщений по расписанию.
     */
    public function send() : void
    {
        $records = $this->queue->getNextSendingPart($this->messenger);

        if ($records->isEmpty()) {
            return;
        }

        /**
         * Удаление сообщений из очереди до отправки — пачка не будет
         * забрана параллельным запуском.
         */
        $this->queue->deleteByIds($records->pluck('id')->all());

        /**
         * Отправка сообщений.
         *
         * @var NotificationMessengerQueue $record
         */
        foreach ($records as $record) {
            try {
                $data = SenderDto::from([
                    'chat_id' => $record->messenger_id,
                    'message' => $this->message_formatter->prepareMessage($record->message),
                ]);

                $response = $this->sender->send($data);

                if (! $response->success) {
                    if ($response->should_unsubscribe) {
                        /**
                         * Получатель заблокировал бота — в боевой
                         * реализации здесь отписка пользователя.
                         */
                        Log::warning("[{$this->messenger}] Получатель {$record->messenger_id} недоступен, требуется отписка.");
                    } elseif ($response->should_retry) {
                        $this->returnToQueue($record);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);

                Log::error(
                    "Ошибка при отправке сообщения в мессенджер. Chat ID: {$record->messenger_id}.",
                    ['error' => $exception->getMessage()]
                );

                $this->returnToQueue($record);
            }
        }
    }

    // ****************************************************************
    // *************************** Support ****************************
    // ****************************************************************

    /**
     * Возврат сообщения в очередь.
     */
    protected function returnToQueue(NotificationMessengerQueue $record) : void
    {
        $this->queue->addMail($this->messenger, (string) $record->messenger_id, $record->message);
    }
}
