<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VaccinationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('health.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'uuid'],
            'animal_id' => ['sometimes', 'nullable', 'integer', 'exists:animals,id'],
            'condition_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'vaccination_date' => ['required', 'date', 'before_or_equal:today'],
            'vaccine_name' => ['required', 'string', 'max:255'],
            'dose' => ['required', 'string', 'max:255'],
            'route' => ['required', 'string', 'in:IM,SC,Oral'],
            'pen_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'next_due_date' => ['sometimes', 'nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'vaccination_date.required' => 'A vaccination date is required.',
            'vaccine_name.required' => 'A vaccine name is required.',
            'dose.required' => 'A dose is required.',
            'route.required' => 'A route is required.',
            'route.in' => 'The route must be IM, SC, or Oral.',
        ];
    }
}
