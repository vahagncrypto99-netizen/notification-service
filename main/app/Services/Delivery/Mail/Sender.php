<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Models\NotificationMailQueue;
use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\Mail\Repository\MailQueueRepository;
use App\Services\Delivery\PermanentDeliveryException;

abstract class Sender implements MailSenderInterface
{
    /**
     * Sender constructor.
     */
    public function __construct(
        protected MailQueueRepository $mail_queue_repository,
    ) {
        //
    }

    /**
     * Отправка письма.
     *
     * Ошибки не подавляются: вызывающий контур (джоба или крон)
     * сам решает — ретрай или терминальный отказ.
     */
    public function send(SenderDto $data) : void
    {
        /**
         * Невалидный адрес — неисправимый отказ, ретраи бессмысленны
         * (в боевой реализации здесь же — отписка/блэклист и метрики).
         */
        if (filter_var($data->to_email, FILTER_VALIDATE_EMAIL) === false) {
            throw new PermanentDeliveryException("Невалидный адрес получателя: {$data->to_email}.");
        }

        /**
         * Постановка в очередь канала, если есть такая настройка.
         */
        if ($data->queue) {
            $this->addToQueue($data, $this->getSenderName());

            return;
        }

        /**
         * Прямая отправка письма.
         */
        $this->sendProcess($data);
    }

    /**
     * Отправка письма конкретным сендером.
     */
    abstract protected function sendProcess(SenderDto $data) : void;

    /**
     * Название сендера.
     */
    abstract protected function getSenderName() : string;

    /**
     * Постановка письма в очередь канала на отправку.
     */
    protected function addToQueue(SenderDto $data, ?string $sender = null) : void
    {
        /**
         * Идемпотентность повторной постановки: на одно уведомление —
         * не больше одной записи в очереди (передиспатч watchdog-ом
         * не приводит к дублю письма).
         */
        if ($data->notification_id !== null
            && $this->mail_queue_repository->existsForNotification($data->notification_id)) {
            return;
        }

        /** @var NotificationMailQueue $record */
        $record = $this->mail_queue_repository->new();

        $record->notification_id = $data->notification_id;

        $record->from_email = $data->from_email;
        $record->from_name = $data->from_name;
        $record->to_email = $data->to_email;
        $record->subject = $data->subject;
        $record->message = $data->message;
        $record->additionally = $data->additionally;
        $record->sender = $sender;
        $record->priority = (int) $data->priority;
        $record->send_at = $data->send_at;

        $record->save();
    }
}
