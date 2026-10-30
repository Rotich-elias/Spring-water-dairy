<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'imageable_type' => ['required', 'string', Rule::in(array_keys(config('images.types', [])))],
            'imageable_id' => ['required', 'integer', 'min:1'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('images.max_size', 5120)],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
