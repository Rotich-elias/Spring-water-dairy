<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lookups.edit');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
