<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
