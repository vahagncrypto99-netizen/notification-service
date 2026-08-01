<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Models\NotificationMessengerQueue;
use App\Repository\Base;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Base<NotificationMessengerQueue>
 */
class MessengerQueueRepository extends Base
{
    /**
     * Размер пачки на одну отправку.
     */
    protected const SENDING_BATCH_SIZE = 100;

    /**
     * Инициализация репозитория.
     *
     * @return class-string<NotificationMessengerQueue>
     */
    protected function init() : string
    {
        return NotificationMessengerQueue::class;
    }

    /**
     * Добавление сообщения в очередь мессенджера.
     */
    public function addMail(string $messenger, string $messenger_id, string $message, ?string $send_at = null) : void
    {
        /** @var NotificationMessengerQueue $record */
        $record = $this->new();

        $record->messenger = $messenger;
        $record->messenger_id = $messenger_id;
        $record->message = $message;
        $record->send_at = $send_at;

        $record->save();
    }

    /**
     * Пачка сообщений мессенджера на отправку.
     *
     * @return Collection<int, NotificationMessengerQueue>
     */
    public function getNextSendingPart(string $messenger) : Collection
    {
        return $this->query()->where('messenger', $messenger)->orderBy('id')->limit(self::SENDING_BATCH_SIZE)->get();
    }

    /**
     * Удаление сообщений из очереди.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteByIds(array $ids) : void
    {
        $this->query()->whereIn('id', $ids)->delete();
    }
}
