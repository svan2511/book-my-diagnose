<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'booking_number' => $this->booking_number,
            'status' => $this->status,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'patient' => [
                'name' => $this->patient_name,
                'age' => $this->patient_age,
                'gender' => $this->patient_gender,
                // Masked in lists; full value only in detail endpoints.
                'phone' => self::maskPhone($this->patient_phone),
            ],
            'city' => $this->city,
            // GPS pin for the dispatch board (admin endpoints only).
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'tests' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'name' => $i->test_name_snapshot,
                'code' => $i->test_code_snapshot,
                'price' => (float) $i->price_snapshot,
            ])),
            'collection_status' => $this->whenLoaded('collection', fn () => $this->collection?->status),
            'report_ready' => $this->whenLoaded('latestReport', fn () => (bool) $this->latestReport),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function maskPhone(?string $phone): ?string
    {
        if (! $phone || strlen($phone) < 4) {
            return $phone;
        }

        return '+91 '.str_repeat('X', strlen($phone) - 4).substr($phone, -4);
    }
}
