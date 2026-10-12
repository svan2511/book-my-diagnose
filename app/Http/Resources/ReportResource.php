<?php

namespace App\Http\Resources;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'booking_number' => $this->whenLoaded('booking', fn () => $this->booking->booking_number),
            'file_name' => $this->original_name,
            'mime' => $this->mime,
            'file_size' => $this->file_size,
            'status' => $this->status,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            // Signed, expiring — never a permanent public URL (dev-prd §73).
            'access_url' => app(ReportService::class)->accessUrl($this->resource, 24 * 60),
        ];
    }
}
