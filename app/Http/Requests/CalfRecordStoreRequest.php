<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalfRecordStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calves.create');
    }

    public function rules(): array
    {
        return [
            'animal_id' => ['required', 'integer', 'exists:animals,id'],
            'colostrum_litres' => ['nullable', 'numeric', 'min:0'],
            'colostrum_hours' => ['nullable', 'integer', 'min:0'],
            'navel_dip' => ['sometimes', 'boolean'],
            'disbudding' => ['sometimes', 'boolean'],
            'weaning_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'weaning_weight' => ['nullable', 'numeric', 'min:0'],
            'birth_weight' => ['nullable', 'numeric', 'min:0'],
            'outcome' => ['sometimes', 'required', 'in:live,stillborn,deceased'],
        ];
    }
}
