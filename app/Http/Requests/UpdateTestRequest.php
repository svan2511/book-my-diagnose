<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('category_id') && ! is_numeric($this->input('category_id'))) {
            $cat = \App\Models\TestCategory::where('uuid', $this->input('category_id'))->first();
            if ($cat) {
                $this->merge(['category_id' => $cat->id]);
            }
        }
    }

    public function rules(): array
    {
        // {test} arrives as a uuid string or code (see TestController::resolve),
        // never as a bound model — resolve the numeric id for the unique ignore.
        $param = $this->route('test');
        $testId = $param instanceof \App\Models\LabTest
            ? $param->id
            : \App\Models\LabTest::where('uuid', $param)->orWhere('code', strtoupper((string) $param))->value('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:1000'],
            'code' => ['sometimes', 'required', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('lab_tests', 'code')->ignore($testId)],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:test_categories,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'preparation_instructions' => ['nullable', 'string', 'max:3000'],
            'sample_type' => ['nullable', 'string', 'max:64'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'mrp' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'parameter_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'home_collection_available' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'is_nabl' => ['nullable', 'boolean'],
            'is_outsourced' => ['nullable', 'boolean'],
        ];
    }
}
