<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'status' => ['sometimes', 'in:available,unavailable'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
