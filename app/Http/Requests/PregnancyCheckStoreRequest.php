<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Illuminate\Foundation\Http\FormRequest;

class PregnancyCheckStoreRequest extends FormRequest
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
            'check_date' => ['required', 'date', 'before_or_equal:today'],
            'pd_result_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'result' => ['required', 'in:pregnant,open,recheck'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.unique' => 'This pregnancy check has already been recorded.',
            'check_date.before_or_equal' => 'Check date cannot be in the future.',
            'result.in' => 'Result must be pregnant, open, or recheck.',
        ];
    }
}
