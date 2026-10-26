<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setup.complete');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'farm_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:8'],
            'units' => ['sometimes', 'nullable', 'in:metric,imperial'],
            'pens' => ['sometimes', 'array', 'max:100'],
            'pens.*' => ['string', 'max:100', 'distinct'],
            'breeds' => ['sometimes', 'array', 'max:100'],
            'breeds.*' => ['string', 'max:100', 'distinct'],
        ];
    }
}
