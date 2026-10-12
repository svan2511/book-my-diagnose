<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_number' => ['required', 'string', 'max:32'],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
