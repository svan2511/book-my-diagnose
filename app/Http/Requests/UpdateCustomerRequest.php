<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }
}
