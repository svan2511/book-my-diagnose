<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'booking_number' => $this->booking_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'patient' => [
                'name' => $this->patient_name,
                'age' => $this->patient_age,
                'gender' => $this->patient_gender,
                'phone' => $this->patient_phone,
                'email' => $this->patient_email,
            ],
            'location' => [
                'address' => $this->address,
                'area' => $this->area,
                'city' => $this->city,
                'state' => $this->state,
                'pincode' => $this->pincode,
                'landmark' => $this->landmark,
                'latitude' => $this->latitude ? (float) $this->latitude : null,
                'longitude' => $this->longitude ? (float) $this->longitude : null,
            ],
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'tests' => BookingItemResource::collection($this->whenLoaded('items')),
            'collection' => new CollectionResource($this->whenLoaded('collection')),
            'reports' => ReportResource::collection($this->whenLoaded('reports')),
            'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'notes' => $h->notes,
                'at' => $h->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
