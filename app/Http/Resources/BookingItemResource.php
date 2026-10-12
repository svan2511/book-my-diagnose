<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->test_name_snapshot,
            'code' => $this->test_code_snapshot,
            'price' => (float) $this->price_snapshot,
        ];
    }
}
