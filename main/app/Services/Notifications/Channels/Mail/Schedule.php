<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels\Mail;

use App\Models\NotificationMailQueue;
use App\Services\Notifications\Channels\Mail\Dto\SenderDto;
use App\Services\Notifications\Notification;
use App\Services\Notifications\Repositories\MailQueueRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class Schedule
{
    /**
     * Schedule constructor.
     */
    public function __construct(
        protected MailQueueRepository $mail_queue_repository,
        protected Notification $notification,
    ) {
        //
    }

    /**
     * Отправка писем по расписанию: выборка пачки по приоритету
     * с учётом отложенной отправки, удаление из очереди, прямая
     * отправка каждым назначенным сендером.
     */
    public function send() : void
    {
        $records = $this->mail_queue_repository->getForSend();

        if ($records->isEmpty()) {
            return;
        }

        /**
         * Удаление писем из очереди до отправки — пачка не будет
         * забрана параллельным запуском.
         */
        $this->mail_queue_repository->deleteMails($records->pluck('id')->all());

        /**
         * Отправка писем.
         *
         * @var NotificationMailQueue $record
         */
        foreach ($records as $record) {
            try {
                $this->notification->mail($record->sender)->send(SenderDto::from([
                    'from_email' => $record->from_email,
                    'from_name' => $record->from_name,
                    'to_email' => $record->to_email,
                    'subject' => $record->subject,
                    'message' => $record->message,
                    'additionally' => $record->additionally,
                    'priority' => $record->priority,
                    'queue' => false,
                ]));
            } catch (Throwable $exception) {
                report($exception);

                Log::error(
                    'Ошибка при отправке письма из очереди канала. Почта: '.$record->to_email,
                    ['error' => $exception->getMessage()]
                );
            }
        }
    }
}
