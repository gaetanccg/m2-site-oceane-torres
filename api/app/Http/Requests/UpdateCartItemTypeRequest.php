<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', 'string', 'in:digital,print_10x15,print_15x20'],
            'session_id' => ['nullable', 'string'],
        ];
    }
}
