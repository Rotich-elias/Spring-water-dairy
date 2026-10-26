<?php

namespace App\Http\Requests;

use App\Domain\Alerts\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $task = $this->route('task');

        if ($user->can('tasks.update')) {
            return true;
        }

        return $task instanceof Task
            && $user->can('tasks.complete')
            && (int) $task->user_id === (int) $user->id;
    }

    public function rules(): array
    {
        // Users with only tasks.complete may tick their own task and leave a note.
        if (! $this->user()->can('tasks.update')) {
            return [
                'is_completed' => ['required', 'boolean'],
                'completion_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ];
        }

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'required', 'string', 'in:breeding,health,milk,general'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'is_completed' => ['sometimes', 'boolean'],
            'completion_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
