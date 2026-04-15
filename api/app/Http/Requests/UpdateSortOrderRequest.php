<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSortOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array'],
            'photos.*.id' => ['required', 'exists:photos,id'],
            'photos.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
