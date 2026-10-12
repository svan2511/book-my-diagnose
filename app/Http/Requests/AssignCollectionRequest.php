<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Accept either the numeric id or the public uuid from staff search.
        if ($this->filled('staff_id') && ! is_numeric($this->input('staff_id'))) {
            $staff = \App\Models\Staff::where('uuid', $this->input('staff_id'))->first();
            if ($staff) {
                $this->merge(['staff_id' => $staff->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
