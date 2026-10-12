<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'age' => $this->age,
            'total_bookings' => $this->whenCounted('bookings_count', $this->bookings_count ?? null),
            'last_booking_at' => $this->when(
                $this->relationLoaded('bookings') && $this->bookings->isNotEmpty(),
                fn () => $this->bookings->max('created_at')?->toIso8601String()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
