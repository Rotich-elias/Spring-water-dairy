<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppearanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appearance.update');
    }

    public function rules(): array
    {
        return [
            'theme_mode' => ['required', 'string', Rule::in(['light', 'dark', 'system'])],
            'theme_preset' => ['required', 'string', Rule::in(['evergreen', 'ocean', 'sunrise', 'violet', 'slate'])],
            'theme_primary' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_accent' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'farm_name' => ['required', 'string', 'max:120'],
        ];
    }
}
