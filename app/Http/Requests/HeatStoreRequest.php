<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeatStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('breeding.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'uuid'],
            'animal_id' => ['required', 'integer', 'exists:animals,id'],
            'heat_date' => ['required', 'date', 'before_or_equal:today'],
            'observed_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.unique' => 'This heat record has already been entered.',
            'heat_date.before_or_equal' => 'Heat date cannot be in the future.',
        ];
    }
}
