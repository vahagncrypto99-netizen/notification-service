<?php

declare(strict_types=1);

namespace App\Base\Notification\Repository;

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Models\Notification;
use App\Repository\Base;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Base<Notification>
 */
class NotificationRepository extends Base
{
    /**
     * Инициализация репозитория.
     *
     * @return class-string<Notification>
     */
    protected function init() : string
    {
        return Notification::class;
    }

    /**
     * История уведомлений пользователя с фильтрами по статусу и каналу.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    public function historyForUser(
        int $user_id,
        ?NotificationStatusEnum $status = null,
        ?ChannelEnum $channel = null,
        int $per_page = 15
    ) : LengthAwarePaginator {
        return $this->query()->where('user_id', $user_id)->when(
            $status,
            fn ($query) => $query->where('status', $status)
        )->when(
            $channel,
            fn ($query) => $query->where('channel', $channel)
        )->orderByDesc('id')->paginate($per_page);
    }

    /**
     * Уведомления, зависшие в обработке дольше порога.
     *
     * @return Collection<int, Notification>
     */
    public function getStuckInProcessing(Carbon $stuck_before, int $limit = 100) : Collection
    {
        return $this->query()->where(
            'status',
            NotificationStatusEnum::Processing
        )->where(
            'updated_at',
            '<',
            $stuck_before
        )->orderBy('id')->limit($limit)->get();
    }

    /**
     * Переход processing → sent.
     * Применяется только если уведомление всё ещё в processing.
     *
     * @return bool сработал ли переход
     */
    public function markAsSent(int $notification_id) : bool
    {
        return (bool) $this->query()->whereKey($notification_id)->where(
            'status',
            NotificationStatusEnum::Processing
        )->update(['status' => NotificationStatusEnum::Sent]);
    }

    /**
     * Переход processing → failed с фиксацией ошибки.
     * Применяется только если уведомление всё ещё в processing.
     *
     * @return bool сработал ли переход
     */
    public function markAsFailed(int $notification_id, ?string $error) : bool
    {
        return (bool) $this->query()->whereKey($notification_id)->where(
            'status',
            NotificationStatusEnum::Processing
        )->update([
            'status' => NotificationStatusEnum::Failed,
            'last_error' => $error,
        ]);
    }

    /**
     * Агрегация уведомлений пользователя за период: по каждому каналу —
     * всего уведомлений и из них с ошибкой. Считается одним SQL-запросом
     * index-only scan-ом по покрывающему индексу
     * notifications_report_aggregate_index (user_id, created_at) include (channel, status).
     *
     * @return array<int, array{channel: string, total: int, failed: int}>
     */
    public function aggregateByChannel(
        int $user_id,
        Carbon $period_from,
        Carbon $period_to
    ) : array {
        $failed = NotificationStatusEnum::Failed->value;

        /**
         * toBase(): строки агрегата — не сущности, гидрация в модель
         * (с кастами) здесь не нужна.
         */
        $rows = $this->query()->toBase()->selectRaw(
            'channel,'
            .' count(*) as total,'
            .' sum(case when status = ? then 1 else 0 end) as failed',
            [$failed]
        )->where('user_id', $user_id)->whereBetween('created_at', [
            $period_from->copy()->startOfDay(),
            $period_to->copy()->endOfDay(),
        ])->groupBy('channel')->orderBy('channel')->get();

        return $rows->map(fn ($row) => [
            'channel' => (string) $row->channel,
            'total' => (int) $row->total,
            'failed' => (int) $row->failed,
        ])->all();
    }
}
