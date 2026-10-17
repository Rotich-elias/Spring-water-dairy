<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PregnancyCheckStoreRequest;
use App\Http\Resources\PregnancyCheckResource;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Breeding\Services\BreedingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PregnancyChecksController extends Controller
{
    public function __construct(private BreedingService $breeding) {}

    public function index(Request $request)
    {
        Gate::authorize('breeding.view');

        $query = PregnancyCheck::query()->with(['animal', 'pdResult']);

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('result')) {
            $query->where('result', $request->result);
        }

        $checks = $query->orderByDesc('check_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => PregnancyCheckResource::collection($checks),
            'meta' => [
                'page' => $checks->currentPage(),
                'per_page' => $checks->perPage(),
                'total' => $checks->total(),
                'last_page' => $checks->lastPage(),
            ],
        ]);
    }

    public function store(PregnancyCheckStoreRequest $request)
    {
        $check = $this->breeding->recordPregnancyCheck($request->validated());

        return response()->json(['data' => new PregnancyCheckResource($check->load(['animal', 'pdResult']))], 201);
    }

    public function show(PregnancyCheck $check)
    {
        Gate::authorize('breeding.view', $check);

        return response()->json(['data' => new PregnancyCheckResource($check->load(['animal', 'pdResult']))]);
    }

    public function update(Request $request, PregnancyCheck $check)
    {
        Gate::authorize('breeding.update', $check);

        $check->update($request->validate([
            'result' => ['sometimes', 'required', 'in:pregnant,open,recheck'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]));
        $check->increment('version');

        return response()->json(['data' => new PregnancyCheckResource($check->fresh()->load(['animal', 'pdResult']))]);
    }

    public function destroy(PregnancyCheck $check)
    {
        Gate::authorize('breeding.delete', $check);

        $check->delete();

        return response()->json(null, 204);
    }
}
