<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SemenStrawStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('breeding.create');
    }

    public function rules(): array
    {
        return [
            'bull_code' => ['required', 'string', 'max:50', 'unique:semen_straws,bull_code'],
            'bull_name' => ['nullable', 'string', 'max:255'],
            'breed' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
