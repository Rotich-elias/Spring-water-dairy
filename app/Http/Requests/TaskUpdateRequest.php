<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tasks.update');
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'required', 'string', 'in:breeding,health,milk,general'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'is_completed' => ['sometimes', 'boolean'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
