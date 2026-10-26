<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TreatmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('health.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'uuid'],
            'animal_id' => ['required', 'integer', 'exists:animals,id'],
            'treatment_date' => ['required', 'date', 'before_or_equal:today'],
            'condition_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'drug_name' => ['required', 'string', 'max:255'],
            'dose' => ['required', 'string', 'max:255'],
            'route' => ['required', 'string', 'in:IM,IV,SC,Oral,Topical'],
            'milk_withholding_days' => ['sometimes', 'required', 'integer', 'min:0'],
            'meat_withholding_days' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.required' => 'A client UUID is required for offline sync.',
            'animal_id.required' => 'An animal is required.',
            'treatment_date.required' => 'A treatment date is required.',
            'treatment_date.before_or_equal' => 'The treatment date cannot be in the future.',
            'drug_name.required' => 'A drug name is required.',
            'dose.required' => 'A dose is required.',
            'route.required' => 'A route is required.',
            'route.in' => 'The route must be IM, IV, SC, Oral, or Topical.',
        ];
    }
}
