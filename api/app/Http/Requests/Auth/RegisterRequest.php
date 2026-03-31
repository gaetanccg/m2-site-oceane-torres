<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'gdpr_consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'gdpr_consent.required' => 'Vous devez accepter la politique de confidentialité.',
            'gdpr_consent.accepted' => 'Vous devez accepter la politique de confidentialité.',
        ];
    }
}
