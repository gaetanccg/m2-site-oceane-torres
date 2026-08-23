<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResendSchoolSessionMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:email,sms'],
            // Absent : on relance tous les contacts jamais partis ou en echec.
            'contact_ids' => ['sometimes', 'array', 'max:500'],
            'contact_ids.*' => ['uuid', 'exists:school_session_contacts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Le canal d\'envoi est requis (email ou sms).',
        ];
    }
}
