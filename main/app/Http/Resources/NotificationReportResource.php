<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin NotificationReport
 */
#[OA\Schema(
    schema: 'Report',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 42),
        new OA\Property(property: 'period_from', type: 'string', format: 'date', example: '2026-07-01'),
        new OA\Property(property: 'period_to', type: 'string', format: 'date', example: '2026-08-01'),
        new OA\Property(
            property: 'status',
            type: 'string',
            example: 'done',
            enum: ['pending', 'processing', 'done', 'failed']
        ),
        new OA\Property(
            property: 'error',
            type: 'string',
            example: null,
            nullable: true
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class NotificationReportResource extends JsonResource
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
            'user_id' => $this->user_id,
            'period_from' => $this->period_from->toDateString(),
            'period_to' => $this->period_to->toDateString(),
            'status' => $this->status->value,
            'error' => $this->error,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
