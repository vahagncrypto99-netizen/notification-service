<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationReport
 */
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
