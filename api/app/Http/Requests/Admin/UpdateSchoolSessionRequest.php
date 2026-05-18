<?php

namespace App\Http\Requests\Admin;

use App\Rules\SmsTemplateRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'gallery_message' => ['nullable', 'string', 'max:2000'],
            'sms_template' => ['nullable', 'string', 'max:320', new SmsTemplateRule],
        ];
    }
}
