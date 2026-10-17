<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('health.update');
    }

    public function rules(): array
    {
        return [
            'drug_name' => ['sometimes', 'required', 'string', 'max:255'],
            'dose' => ['sometimes', 'required', 'string', 'max:255'],
            'route' => ['sometimes', 'required', 'string', 'in:IM,IV,SC,Oral,Topical'],
            'milk_withholding_days' => ['sometimes', 'required', 'integer', 'min:0'],
            'meat_withholding_days' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
