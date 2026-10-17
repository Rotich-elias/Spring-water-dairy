<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalvingStoreRequest;
use App\Http\Resources\CalvingResource;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Breeding\Services\CalvingAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CalvingsController extends Controller
{
    public function __construct(private CalvingAction $calvingAction) {}

    public function index(Request $request)
    {
        Gate::authorize('breeding.view');

        $query = Calving::query()->with(['dam', 'easeScore']);

        if ($request->filled('dam_id')) {
            $query->where('dam_id', $request->dam_id);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('calving_date', [$request->from, $request->to]);
        }

        $calvings = $query->orderByDesc('calving_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => CalvingResource::collection($calvings),
            'meta' => [
                'page' => $calvings->currentPage(),
                'per_page' => $calvings->perPage(),
                'total' => $calvings->total(),
                'last_page' => $calvings->lastPage(),
            ],
        ]);
    }

    public function store(CalvingStoreRequest $request)
    {
        $result = $this->calvingAction->execute($request->validated());

        return response()->json([
            'data' => [
                'calving' => new CalvingResource($result['calving']->load(['dam', 'easeScore'])),
                'calves' => \App\Http\Resources\AnimalResource::collection($result['calves']),
                'lactation' => new \App\Http\Resources\LactationResource($result['lactation']),
                'dam' => new \App\Http\Resources\AnimalResource($result['dam']->load(['category', 'pen', 'status', 'breed'])),
            ],
        ], 201);
    }

    public function show(Calving $calving)
    {
        Gate::authorize('breeding.view', $calving);

        return response()->json(['data' => new CalvingResource($calving->load(['dam', 'easeScore']))]);
    }

    public function update(Request $request, Calving $calving)
    {
        Gate::authorize('breeding.update', $calving);

        $calving->update($request->validate([
            'ease_score_id' => ['sometimes', 'nullable', 'integer', 'exists:lookups,id'],
            'retained_placenta' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]));
        $calving->increment('version');

        return response()->json(['data' => new CalvingResource($calving->fresh()->load(['dam', 'easeScore']))]);
    }

    public function destroy(Calving $calving)
    {
        Gate::authorize('breeding.delete', $calving);

        $calving->delete();

        return response()->json(null, 204);
    }
}
