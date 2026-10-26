<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnimalExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animals.delete');
    }

    public function rules(): array
    {
        return [
            'exit_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
