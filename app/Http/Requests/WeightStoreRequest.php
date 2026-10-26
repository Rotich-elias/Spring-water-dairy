<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WeightStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calves.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'uuid'],
            'animal_id' => ['required', 'integer', 'exists:animals,id'],
            'weight_date' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.unique' => 'This weight record has already been entered.',
            'weight_date.before_or_equal' => 'Weight date cannot be in the future.',
        ];
    }
}
