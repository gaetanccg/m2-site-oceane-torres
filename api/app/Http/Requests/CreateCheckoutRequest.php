<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = Auth::guard('sanctum')->user();

        $rules = [
            'guest_first_name' => $user ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'guest_last_name' => $user ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'session_id' => ['nullable', 'string'],
            'cgv_accepted' => ['required', 'accepted'],
        ];

        $rules['guest_email'] = $user
            ? ['nullable', 'email']
            : ['required', 'email'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'guest_first_name.required' => 'Le prénom est obligatoire.',
            'guest_last_name.required' => 'Le nom est obligatoire.',
            'cgv_accepted.required' => 'Vous devez accepter les Conditions Générales de Vente.',
            'cgv_accepted.accepted' => 'Vous devez accepter les Conditions Générales de Vente.',
        ];
    }
}
