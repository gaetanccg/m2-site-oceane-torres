<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAsyncPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:15'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi', 'max:51200'],
            'batch_id' => ['required', 'string'],
        ];
    }
}
