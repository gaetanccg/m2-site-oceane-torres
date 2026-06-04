<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGiftCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }

    public function rules(): array
    {
        $isPercent = $this->input('type') === 'percent';

        return [
            'code' => ['nullable', 'string', 'max:24', 'regex:/^[A-Z0-9-]+$/', Rule::unique('gift_codes', 'code')],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'numeric', 'min:0.01', $isPercent ? 'max:100' : 'max:100000'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0.01', Rule::prohibitedIf(! $isPercent)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
