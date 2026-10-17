<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MilkRecordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('milk.update');
    }

    public function rules(): array
    {
        return [
            'litres' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'cmt_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5'],
            'scc' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
