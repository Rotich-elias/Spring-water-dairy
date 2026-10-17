<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LactationDryOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('milk.update');
    }

    public function rules(): array
    {
        return [
            'drying_off_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
