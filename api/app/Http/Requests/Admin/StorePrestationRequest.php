<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_text' => ['nullable', 'string', 'max:100'],
            'price_unit' => ['nullable', 'string', 'max:100'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'background_image' => ['nullable', 'string', 'max:500'],
            'background_opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'disclaimer' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ];
    }
}
