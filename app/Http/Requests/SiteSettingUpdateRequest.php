<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('site.update') ?? false;
    }

    /**
     * Only keys declared in config('site.defaults') can be updated. Unknown
     * keys are dropped by validated() and never reach the database.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (config('site.defaults', []) as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'integer' => ['sometimes', 'integer'],
                'boolean' => ['sometimes', 'boolean'],
                'decimal', 'float' => ['sometimes', 'numeric'],
                'json' => ['sometimes', 'array'],
                default => ['sometimes', 'string', 'max:5000'],
            };
        }

        return $rules;
    }
}
