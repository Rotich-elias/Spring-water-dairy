<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalvingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('breeding.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'uuid'],
            'dam_id' => ['required', 'integer', 'exists:animals,id'],
            'calving_date' => ['required', 'date', 'before_or_equal:today'],
            'ease_score_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'retained_placenta' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'calves' => ['required', 'array', 'min:1'],
            'calves.*.ear_tag' => ['required', 'string', 'max:255', 'unique:animals,ear_tag'],
            'calves.*.sex' => ['required', 'in:M,F'],
            'calves.*.birth_weight' => ['nullable', 'numeric', 'min:0'],
            'calves.*.outcome' => ['required', 'in:live,stillborn,deceased'],
            'calves.*.colostrum_litres' => ['nullable', 'numeric', 'min:0'],
            'calves.*.colostrum_hours' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.unique' => 'This calving has already been recorded.',
            'calving_date.before_or_equal' => 'Calving date cannot be in the future.',
            'calves.*.ear_tag.unique' => 'The calf ear tag has already been taken.',
            'calves.*.sex.in' => 'Calf sex must be M (male) or F (female).',
            'calves.*.outcome.in' => 'Calf outcome must be live, stillborn, or deceased.',
        ];
    }
}
