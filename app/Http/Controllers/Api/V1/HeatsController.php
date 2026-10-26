<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Services\BreedingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeatStoreRequest;
use App\Http\Resources\HeatResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HeatsController extends Controller
{
    public function __construct(private BreedingService $breeding) {}

    public function index(Request $request)
    {
        Gate::authorize('breeding.view');

        $query = Heat::query()->with('animal');

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('heat_date', [$request->from, $request->to]);
        }

        $heats = $query->orderByDesc('heat_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => HeatResource::collection($heats),
            'meta' => [
                'page' => $heats->currentPage(),
                'per_page' => $heats->perPage(),
                'total' => $heats->total(),
                'last_page' => $heats->lastPage(),
            ],
        ]);
    }

    public function store(HeatStoreRequest $request)
    {
        $heat = $this->breeding->recordHeat($request->validated());

        return response()->json(['data' => new HeatResource($heat->load('animal'))], 201);
    }

    public function show(Heat $heat)
    {
        Gate::authorize('breeding.view', $heat);

        return response()->json(['data' => new HeatResource($heat->load('animal'))]);
    }

    public function update(Request $request, Heat $heat)
    {
        Gate::authorize('breeding.update', $heat);

        $heat->update($request->validate([
            'observed_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]));
        $heat->increment('version');

        return response()->json(['data' => new HeatResource($heat->fresh()->load('animal'))]);
    }

    public function destroy(Heat $heat)
    {
        Gate::authorize('breeding.delete', $heat);

        $heat->delete();

        return response()->json(null, 204);
    }
}
