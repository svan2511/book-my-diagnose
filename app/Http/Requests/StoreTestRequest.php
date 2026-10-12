<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Accept either the numeric id or the public uuid.
        if ($this->filled('category_id') && ! is_numeric($this->input('category_id'))) {
            $cat = \App\Models\TestCategory::where('uuid', $this->input('category_id'))->first();
            if ($cat) {
                $this->merge(['category_id' => $cat->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('lab_tests', 'code')],
            'category_id' => ['required', 'integer', 'exists:test_categories,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'preparation_instructions' => ['nullable', 'string', 'max:3000'],
            'sample_type' => ['nullable', 'string', 'max:64'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'mrp' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'gte:price'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'parameter_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'home_collection_available' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'is_nabl' => ['nullable', 'boolean'],
            'is_outsourced' => ['nullable', 'boolean'],
        ];
    }
}
