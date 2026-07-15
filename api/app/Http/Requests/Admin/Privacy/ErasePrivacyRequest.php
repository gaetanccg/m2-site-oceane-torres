<?php

namespace App\Http\Requests\Admin\Privacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ErasePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['email', 'phone', 'order_number'])],
            'value' => ['required', 'string', 'max:255'],
            // Confirmation tapée : doit correspondre exactement à la valeur ciblée.
            'confirm' => ['required', 'same:value'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.same' => 'La confirmation ne correspond pas à la valeur à supprimer.',
        ];
    }
}
