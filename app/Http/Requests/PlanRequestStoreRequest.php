<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequestStoreRequest extends FormRequest
{
    /**
     * Authorization is performed in the controller, where the active tenant
     * context is known.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', Rule::exists('plans', 'code')],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
