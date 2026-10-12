<?php

namespace App\Http\Requests;

use App\Enums\CollectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CollectionStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
