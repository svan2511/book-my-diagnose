<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Accept test uuids or ids — resolve uuids to ids for validation.
        if (is_array($this->input('test_ids'))) {
            $mapped = [];
            foreach ($this->input('test_ids') as $t) {
                if (is_numeric($t)) {
                    $mapped[] = (int) $t;
                } elseif (is_string($t)) {
                    $found = \App\Models\LabTest::where('uuid', $t)->orWhere('code', strtoupper($t))->first();
                    $mapped[] = $found ? $found->id : $t;
                }
            }
            $this->merge(['test_ids' => $mapped]);
        }
        // Normalize Indian phone variants (+91-98200..., 91..., 0...) to 10 digits.
        foreach (['phone', 'patient_phone'] as $field) {
            if ($this->filled($field)) {
                $digits = preg_replace('/\D+/', '', (string) $this->input($field));
                $digits = preg_replace('/^(91|0)/', '', $digits ?? '');
                $this->merge([$field => $digits]);
            }
        }
    }

    public function rules(): array
    {
        $indianPhone = ['required', 'string', 'regex:/^[6-9]\d{9}$/'];

        return [
            'test_ids' => ['required', 'array', 'min:1', 'max:10'],
            'test_ids.*' => ['integer', 'exists:lab_tests,id'],

            // Account holder (customer upsert key)
            'name' => ['required', 'string', 'max:120'],
            'phone' => $indianPhone,
            'email' => ['nullable', 'email', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', Rule::in(Gender::values())],

            // Patient
            'patient_name' => ['required', 'string', 'max:120'],
            'patient_age' => ['required', 'integer', 'min:0', 'max:120'],
            'patient_gender' => ['required', Rule::in(Gender::values())],
            'patient_phone' => $indianPhone,
            'patient_email' => ['nullable', 'email', 'max:255'],

            // Location (manual fallback always allowed — geolocation is progressive enhancement)
            'address' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'string', 'regex:/^\d{6}$/'],
            'landmark' => ['required', 'string', 'max:255'],
            // GPS fix is mandatory — orders are accepted by location only.
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid 10-digit Indian mobile number.',
            'patient_phone.regex' => 'Please enter a valid 10-digit Indian mobile number.',
            'pincode.regex' => 'Pincode must be 6 digits.',
            'latitude.required' => 'Location is required. Please share your current GPS location.',
            'longitude.required' => 'Location is required. Please share your current GPS location.',
        ];
    }
}
