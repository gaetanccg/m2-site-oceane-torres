<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendContactRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'gdpr_consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'gdpr_consent.required' => 'Vous devez accepter le traitement de vos données personnelles.',
            'gdpr_consent.accepted' => 'Vous devez accepter le traitement de vos données personnelles.',
        ];
    }
}
