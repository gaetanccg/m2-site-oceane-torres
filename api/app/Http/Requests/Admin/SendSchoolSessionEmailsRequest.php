<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendSchoolSessionEmailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.gallery_id' => ['required', 'uuid', 'exists:galleries,id'],
            'contacts.*.email' => ['required', 'email'],
            'contacts.*.recipient_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'contacts.required' => 'Au moins un contact est requis.',
            'contacts.*.email.required' => 'L\'email est requis pour chaque contact.',
            'contacts.*.email.email' => 'L\'email doit être valide.',
            'contacts.*.recipient_name.required' => 'Le nom du destinataire est requis.',
            'contacts.*.gallery_id.exists' => 'Une des galeries spécifiées n\'existe pas.',
        ];
    }
}
