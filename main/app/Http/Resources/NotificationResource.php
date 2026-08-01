<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Notification
 */
#[OA\Schema(
    schema: 'Notification',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'message', type: 'string', example: 'Ваш заказ подтверждён'),
        new OA\Property(property: 'status', type: 'string', enum: ['processing', 'sent', 'failed'], example: 'sent'),
        new OA\Property(property: 'user_id', type: 'integer', example: 42),
        new OA\Property(property: 'channel', type: 'string', enum: ['email', 'telegram'], example: 'email'),
        new OA\Property(property: 'attempts_count', type: 'integer', example: 1),
        new OA\Property(property: 'last_attempted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_error', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
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
