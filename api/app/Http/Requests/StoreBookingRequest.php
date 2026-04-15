<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'prestation_id' => ['required', 'exists:prestations,id'],
            'date_preferences' => ['required', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'gdpr_consent' => ['required', 'accepted'],
        ];
    }
}
