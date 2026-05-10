<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_id' => ['required', 'uuid', 'exists:photos,id'],
            'product_type' => ['nullable', 'string', 'in:digital,print_10x15,print_15x20,print_scolaire'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'session_id' => ['nullable', 'string'],
        ];
    }
}
