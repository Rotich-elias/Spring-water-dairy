<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnimalStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animals.create');
    }

    public function rules(): array
    {
        $tagPattern = config('app.ear_tag_pattern', '^[A-Z]{3,5}-\d{1,5}$');

        return [
            'client_uuid' => ['sometimes', 'string', 'uuid', Rule::unique('animals')->whereNull('deleted_at')],
            'ear_tag' => ['required', 'string', 'max:255', 'regex:/'.$tagPattern.'/', Rule::unique('animals')->whereNull('deleted_at')],
            'name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:lookups,id'],
            'pen_id' => ['nullable', 'integer', 'exists:lookups,id'],
            'status_id' => ['nullable', 'integer', 'exists:lookups,id'],
            'breed_id' => ['nullable', 'integer', 'exists:lookups,id'],
            'sex' => ['required', 'in:M,F'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'ear_tag.regex' => 'The ear tag format is invalid.',
            'ear_tag.unique' => 'The ear tag has already been taken.',
            'client_uuid.uuid' => 'The client UUID must be a valid UUID.',
            'sex.in' => 'Sex must be M (male) or F (female).',
            'birth_date.before_or_equal' => 'The birth date cannot be in the future.',
        ];
    }
}
