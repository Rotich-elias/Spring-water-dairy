<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;

class ServiceStoreRequest extends FormRequest
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
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'bull_id' => ['nullable', 'integer', 'exists:semen_straws,id'],
            'inseminator' => ['nullable', 'string', 'max:255'],
            'service_type' => ['sometimes', 'required', 'in:AI,Natural'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.unique' => 'This service has already been recorded.',
            'service_date.before_or_equal' => 'Service date cannot be in the future.',
        ];
    }
}
