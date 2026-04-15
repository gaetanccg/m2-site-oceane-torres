<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prestation_id' => ['required', 'exists:prestations,id'],
            'date' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string'],
            'client_form' => ['required', 'array'],
            'client_form.fullname' => ['required', 'string', 'max:255'],
            'client_form.phone' => ['nullable', 'string', 'max:20'],
            'client_form.requirements' => ['nullable', 'string'],
            'client_form.message' => ['nullable', 'string'],
        ];
    }
}
