<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilkSessionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('milk.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'string', 'uuid'],
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'session' => ['required', 'in:morning,evening'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.animal_id' => ['required', 'integer', 'exists:animals,id'],
            'entries.*.litres' => ['required', 'numeric', 'min:0', 'max:100'],
            'entries.*.cmt_score' => ['nullable', 'integer', 'min:0', 'max:5'],
            'entries.*.scc' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_uuid.required' => 'A client UUID is required for offline sync.',
            'entries.required' => 'At least one milk entry is required.',
            'entries.*.litres.max' => 'Litres cannot exceed 100 per cow per session.',
        ];
    }
}
