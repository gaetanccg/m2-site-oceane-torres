<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkToggleDownloadableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['exists:photos,id'],
            'is_downloadable' => ['required', 'boolean'],
        ];
    }
}
