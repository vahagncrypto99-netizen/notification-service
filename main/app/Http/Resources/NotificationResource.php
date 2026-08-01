<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request) : array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'status' => $this->status->value,
            'user_id' => $this->user_id,
            'channel' => $this->channel->value,
            'attempts_count' => $this->attempts_count,
            'last_attempted_at' => $this->last_attempted_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Пагинированная коллекция для payload единого контракта.
     *
     * @param  LengthAwarePaginator<int, Notification>  $paginator
     * @return array<string, mixed>
     */
    public static function paginateCollection(LengthAwarePaginator $paginator) : array
    {
        return [
            'notifications' => self::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
