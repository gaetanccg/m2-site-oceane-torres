<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'days_of_week' => ['sometimes', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'min:1', 'max:7'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'slot_duration_minutes' => ['sometimes', 'integer', 'min:15'],
            'repeat_every_weeks' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }
}
