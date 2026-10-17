<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AnimalBulkStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animals.create');
    }

    public function rules(): array
    {
        return [
            'animals' => ['required', 'array', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'animals.max' => 'A bulk create may include at most 200 animals.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure each row carries a client_uuid for idempotent offline retries.
        foreach ($this->input('animals', []) as $index => $row) {
            if (blank($row['client_uuid'] ?? null)) {
                $this->merge(['animals.'.$index.'.client_uuid' => (string) Str::uuid()]);
            }
        }
    }
}
