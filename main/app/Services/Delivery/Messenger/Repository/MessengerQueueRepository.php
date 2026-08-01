<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger\Repository;

use App\Models\NotificationMessengerQueue;
use App\Repository\Base;
use Carbon\Carbon;
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
    public function addMessage(
        string $messenger,
        string $messenger_id,
        string $message,
        ?string $send_at = null,
        ?int $notification_id = null,
    ) : void {
        /** @var NotificationMessengerQueue $record */
        $record = $this->new();

        $record->notification_id = $notification_id;
        $record->messenger = $messenger;
        $record->messenger_id = $messenger_id;
        $record->message = $message;
        $record->send_at = $send_at;

        $record->save();
    }

    /**
     * Пачка сообщений мессенджера на отправку: отложенные —
     * только с наступившим временем.
     *
     * @return Collection<int, NotificationMessengerQueue>
     */
    public function getNextSendingPart(string $messenger) : Collection
    {
        return $this->query()
            ->where('messenger', $messenger)
            ->where(function ($query) {
                $query->whereNull('send_at')->orWhere('send_at', '<', Carbon::now());
            })
            ->orderBy('id')
            ->limit(self::SENDING_BATCH_SIZE)
            ->get();
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

    /**
     * Есть ли в очереди сообщение по уведомлению.
     */
    public function existsForNotification(int $notification_id) : bool
    {
        return $this->query()->where('notification_id', $notification_id)->exists();
    }

    /**
     * Фиксация неудачной попытки отправки.
     */
    public function registerAttempt(int $id) : void
    {
        $this->query()->whereKey($id)->increment('attempts');
    }
}
