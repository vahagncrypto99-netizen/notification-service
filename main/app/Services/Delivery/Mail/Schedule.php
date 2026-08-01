<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Models\NotificationMailQueue;
use App\Services\Delivery\DeliveryResultHandlerInterface;
use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\Mail\Repository\MailQueueRepository;
use App\Services\Delivery\PermanentDeliveryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Schedule
{
    /**
     * Максимум попыток отправки одного письма кроном.
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * Schedule constructor.
     */
    public function __construct(
        protected MailQueueRepository $mail_queue_repository,
        protected SenderFactory $sender_factory,
        protected DeliveryResultHandlerInterface $delivery_result_handler,
    ) {
        //
    }

    /**
     * Отправка писем по расписанию.
     *
     * Запись покидает очередь только после исхода отправки — упавший
     * между выборкой и отправкой процесс не теряет письма (повторная
     * отправка возможна: канал работает как at-least-once).
     * От параллельной выборки пачки защищает withoutOverlapping
     * задачи планировщика.
     */
    public function send() : void
    {
        $records = $this->mail_queue_repository->getForSend();

        /** @var NotificationMailQueue $record */
        foreach ($records as $record) {
            try {
                $this->sender_factory->mail($record->sender)->send(SenderDto::from([
                    'from_email' => $record->from_email,
                    'from_name' => $record->from_name,
                    'to_email' => $record->to_email,
                    'subject' => $record->subject,
                    'message' => $record->message,
                    'additionally' => $record->additionally,
                    'priority' => $record->priority,
                    'queue' => false,
                ]));

                $this->confirm($record);
            } catch (PermanentDeliveryException $exception) {
                $this->reject($record, $exception->getMessage());
            } catch (Throwable $exception) {
                report($exception);

                Log::error(
                    'Ошибка при отправке письма из очереди канала. Почта: '.$record->to_email,
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
     * Письмо отправлено: удаление из очереди и подтверждение доставки.
     */
    protected function confirm(NotificationMailQueue $record) : void
    {
        $this->mail_queue_repository->deleteMails([$record->id]);

        if ($record->notification_id !== null) {
            $this->delivery_result_handler->sent($record->notification_id);
        }
    }

    /**
     * Терминальный отказ: удаление из очереди и фиксация сбоя доставки.
     */
    protected function reject(NotificationMailQueue $record, string $error) : void
    {
        Log::warning("Письмо {$record->to_email} не будет отправлено: {$error}");

        $this->mail_queue_repository->deleteMails([$record->id]);

        if ($record->notification_id !== null) {
            $this->delivery_result_handler->failed($record->notification_id, $error);
        }
    }

    /**
     * Повтор следующим запуском; после MAX_ATTEMPTS — терминальный отказ.
     */
    protected function retryOrReject(NotificationMailQueue $record, string $error) : void
    {
        if ($record->attempts + 1 >= self::MAX_ATTEMPTS) {
            $this->reject($record, $error);

            return;
        }

        $this->mail_queue_repository->registerAttempt($record->id);
    }
}
