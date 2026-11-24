<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.manage');
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['required', 'string', Rule::exists('plans', 'code')],
        ];
    }
}
