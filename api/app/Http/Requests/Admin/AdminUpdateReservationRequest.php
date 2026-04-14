<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:pending,confirmed,cancelled,completed'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
