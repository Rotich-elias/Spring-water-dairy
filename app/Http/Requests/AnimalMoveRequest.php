<?php

namespace App\Http\Requests;

use App\Domain\Animals\Models\Animal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnimalMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animals.update', $this->route('animal'));
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'pen_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'status_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
