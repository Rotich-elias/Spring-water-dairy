<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('tenants', 'slug')],
            'plan_code' => ['nullable', 'string', Rule::exists('plans', 'code')],
        ];
    }
}
