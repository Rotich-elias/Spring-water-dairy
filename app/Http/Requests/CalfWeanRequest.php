<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalfWeanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calves.update');
    }

    public function rules(): array
    {
        return [
            'weaning_date' => ['required', 'date', 'before_or_equal:today'],
            'weaning_weight' => ['required', 'numeric', 'min:0'],
        ];
    }
}
