<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Canonical 10-digit form so +91/0-prefixed duplicates can't slip past unique.
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
        return [
            'name' => ['required', 'string', 'max:120'],
            // Trashed numbers pass validation so the controller can restore them.
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', Rule::unique('staff', 'phone')->withoutTrashed()],
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
