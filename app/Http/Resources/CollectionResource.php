<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'staff' => $this->whenLoaded('staff', fn () => $this->staff ? [
                'id' => $this->staff->uuid,
                'name' => $this->staff->name,
                'phone' => $this->staff->phone,
            ] : null),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'collected_at' => $this->collected_at?->toIso8601String(),
            'notes' => $this->notes,
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'notes' => $h->notes,
                'at' => $h->created_at?->toIso8601String(),
            ])),
        ];
    }
}
