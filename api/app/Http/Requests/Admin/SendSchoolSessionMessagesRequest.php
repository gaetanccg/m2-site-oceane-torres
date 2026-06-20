<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendSchoolSessionMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:email,sms'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.gallery_id' => ['required', 'uuid', 'exists:galleries,id'],
            'contacts.*.recipient_name' => ['required', 'string', 'max:255'],
            'contacts.*.email' => ['required_if:channel,email', 'nullable', 'email'],
            'contacts.*.phone' => ['required_if:channel,sms', 'nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Le canal d\'envoi est requis (email ou sms).',
            'contacts.required' => 'Au moins un contact est requis.',
            'contacts.*.email.required_if' => 'L\'email est requis pour l\'envoi par email.',
            'contacts.*.phone.required_if' => 'Le numero de telephone est requis pour l\'envoi par SMS.',
            'contacts.*.gallery_id.exists' => 'Une des galeries specifiées n\'existe pas.',
        ];
    }
}
