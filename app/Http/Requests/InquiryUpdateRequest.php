<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InquiryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inquiries.manage') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['new', 'contacted', 'closed'])],
        ];
    }
}
