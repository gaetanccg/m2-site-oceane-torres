<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'assigned_email' => ['nullable', 'email', 'max:255'],
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
            'product_types.*.tiers' => ['nullable', 'array', 'max:3'],
            'product_types.*.tiers.*.min_quantity' => ['required', 'integer', 'min:2'],
            'product_types.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
