<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('phone'));
            if (str_starts_with($digits, '91') && strlen($digits) === 12) {
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
                $digits = substr($digits, 1);
            }
            $this->merge(['phone' => $digits]);
        }
    }

    public function rules(): array
    {
        $param = $this->route('staff');
        $staffId = $param instanceof \App\Models\Staff
            ? $param->id
            : \App\Models\Staff::where('uuid', $param)->value('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^[6-9]\d{9}$/', Rule::unique('staff', 'phone')->ignore($staffId)],
            'role' => ['nullable', Rule::in(['collector', 'supervisor'])],
            'area' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid 10-digit Indian mobile number.',
        ];
    }
}
