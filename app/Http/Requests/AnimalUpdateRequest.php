<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnimalUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animals.update', $this->route('animal'));
    }

    public function rules(): array
    {
        $tagPattern = config('app.ear_tag_pattern', '^[A-Z]{3,5}-\d{1,5}$');

        return [
            'ear_tag' => ['sometimes', 'required', 'string', 'max:255', 'regex:/'.$tagPattern.'/', Rule::unique('animals')->ignore($this->route('animal'))->whereNull('deleted_at')],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:lookups,id'],
            'pen_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'status_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'breed_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'sex' => ['sometimes', 'required', 'in:M,F'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'ear_tag.unique' => 'The ear tag has already been taken.',
            'sex.in' => 'Sex must be M (male) or F (female).',
            'birth_date.before_or_equal' => 'The birth date cannot be in the future.',
        ];
    }
}
