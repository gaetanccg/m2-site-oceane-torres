<?php

namespace App\Http\Requests\Admin\Privacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchPrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'accès admin est déjà garanti par le middleware `admin` du groupe de routes.
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
        ];
    }
}
