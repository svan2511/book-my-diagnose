<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'category' => new TestCategoryResource($this->whenLoaded('category')),
            'description' => $this->description,
            'preparation_instructions' => $this->preparation_instructions,
            'sample_type' => $this->sample_type,
            'sample_detail' => $this->sample_detail,
            'storage_temp' => $this->storage_temp,
            'method' => $this->method,
            'schedule' => $this->schedule,
            'reporting' => $this->reporting,
            'general_instructions' => $this->general_instructions,
            'serial_no' => $this->serial_no,
            'source_sheet' => $this->source_sheet,
            'code_prefix' => $this->code_prefix,
            'is_nabl' => (bool) $this->is_nabl,
            'is_outsourced' => (bool) $this->is_outsourced,
            'is_discontinued' => (bool) $this->is_discontinued,
            'price' => (float) $this->price,
            'mrp' => $this->mrp ? (float) $this->mrp : null,
            'discount_percent' => $this->discountPercent(),
            'turnaround_hours' => $this->turnaround_hours,
            'parameter_count' => $this->parameter_count,
            'home_collection_available' => (bool) $this->home_collection_available,
            'status' => $this->status,
        ];
    }
}
