<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Alerts\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TasksController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('tasks.view');

        $query = Task::query()->with('user');

        if ($request->filled('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }

        $tasks = $query->orderBy('due_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => TaskResource::collection($tasks),
            'meta' => [
                'page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'last_page' => $tasks->lastPage(),
            ],
        ]);
    }

    public function store(TaskStoreRequest $request)
    {
        Gate::authorize('tasks.create');

        $task = Task::create(array_merge($request->validated(), [
            'user_id' => $request->validated('user_id') ?? auth()->id(),
        ]));

        return response()->json(['data' => new TaskResource($task->refresh())], 201);
    }

    public function show(Task $task)
    {
        Gate::authorize('tasks.view', $task);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function update(TaskUpdateRequest $request, Task $task)
    {
        // Authorization is handled by TaskUpdateRequest: managers may edit any
        // task, while tasks.complete holders may only tick their own task off.
        $task->update($request->validated());

        return response()->json(['data' => new TaskResource($task->fresh())]);
    }

    public function destroy(Task $task)
    {
        Gate::authorize('tasks.delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }
}
