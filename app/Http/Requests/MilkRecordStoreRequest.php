<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilkRecordStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('milk.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'uuid'],
            'animal_id' => ['required', 'integer', 'exists:animals,id'],
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'session' => ['required', 'in:morning,evening'],
            'litres' => ['required', 'numeric', 'min:0', 'max:100'],
            'cmt_score' => ['nullable', 'integer', 'min:0', 'max:5'],
            'scc' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
