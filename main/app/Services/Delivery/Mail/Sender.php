<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use App\Models\NotificationMailQueue;
use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\Mail\MailQueueRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * Отправка письма: валидация адреса → очередь канала или прямая
     * отправка конкретным сендером (template method).
     */
    public function send(SenderDto $data) : void
    {
        try {
            /**
             * Проверка адреса на валидность (в боевой реализации здесь же —
             * отписка невалидных/временных/блэклист-адресов и метрики).
             */
            if (filter_var($data->to_email, FILTER_VALIDATE_EMAIL) === false) {
                Log::warning("[{$this->getSenderName()}] Невалидный адрес получателя: {$data->to_email}, письмо пропущено.");

                return;
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
        } catch (Throwable $exception) {
            report($exception);

            Log::error(
                "[{$this->getSenderName()}] Ошибка отправки почты. Адрес {$data->to_email}.",
                ['error' => $exception->getMessage()]
            );
        }
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
        /** @var NotificationMailQueue $record */
        $record = $this->mail_queue_repository->new();

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
