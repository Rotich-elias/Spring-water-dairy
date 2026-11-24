<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tenants.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => [
                'sometimes',
                'nullable',
                'alpha_dash',
                'max:120',
                Rule::unique('tenants', 'slug')->ignore($this->route('tenant')),
            ],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'suspended', 'inactive', 'archived'])],
        ];
    }
}
