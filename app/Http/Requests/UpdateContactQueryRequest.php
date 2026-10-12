<?php

namespace App\Http\Requests;

use App\Enums\ContactQueryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(ContactQueryStatus::values())],
            'assigned_to' => ['nullable', 'string', 'max:120'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
