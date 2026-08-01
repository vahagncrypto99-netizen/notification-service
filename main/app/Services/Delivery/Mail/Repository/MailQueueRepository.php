<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail\Repository;

use App\Models\NotificationMailQueue;
use App\Repository\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Base<NotificationMailQueue>
 */
class MailQueueRepository extends Base
{
    /**
     * Размер пачки на одну отправку.
     */
    protected const SENDING_BATCH_SIZE = 100;

    /**
     * Инициализация репозитория.
     *
     * @return class-string<NotificationMailQueue>
     */
    protected function init() : string
    {
        return NotificationMailQueue::class;
    }

    /**
     * Пачка писем на отправку: по убыванию приоритета, отложенные —
     * только с наступившим временем.
     *
     * @return Collection<int, NotificationMailQueue>
     */
    public function getForSend() : Collection
    {
        return $this->query()->where(function ($query) {
            $query->whereNull('send_at')->orWhere('send_at', '<', Carbon::now());
        })->orderByDesc('priority')->orderBy('id')->limit(self::SENDING_BATCH_SIZE)->get();
    }

    /**
     * Удаление писем из очереди.
     *
     * @param  array<int, int>  $ids
     */
    public function deleteMails(array $ids) : void
    {
        $this->query()->whereIn('id', $ids)->delete();
    }

    /**
     * Есть ли в очереди письмо по уведомлению.
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
