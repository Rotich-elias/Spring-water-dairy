<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Animals\Models\Animal;
use App\Domain\Calves\Models\Weight;
use App\Domain\Calves\Services\CalfGrowthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\WeightStoreRequest;
use App\Http\Resources\WeightResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WeightsController extends Controller
{
    public function __construct(private CalfGrowthService $growth) {}

    public function index(Request $request)
    {
        Gate::authorize('calves.view');

        $query = Weight::query()->with('animal');

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('weight_date', [$request->from, $request->to]);
        }

        $weights = $query->orderByDesc('weight_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => WeightResource::collection($weights),
            'meta' => [
                'page' => $weights->currentPage(),
                'per_page' => $weights->perPage(),
                'total' => $weights->total(),
                'last_page' => $weights->lastPage(),
            ],
        ]);
    }

    public function store(WeightStoreRequest $request)
    {
        Gate::authorize('calves.create');

        $validated = $request->validated();
        $clientUuid = $validated['client_uuid'] ?? null;

        // Idempotency
        if ($clientUuid) {
            $existing = Weight::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return response()->json(['data' => new WeightResource($existing->load('animal'))]);
            }
        }

        $animal = Animal::query()->where('id', $validated['animal_id'])->firstOrFail();
        $previous = $animal->weights()->latest('weight_date')->first();

        $weight = Weight::create(array_merge($validated, [
            'client_uuid' => $clientUuid ?? (string) Str::uuid(),
        ]));

        // Compute ADG if there's a previous weight
        if ($previous) {
            $adg = $this->growth->computeAdg($previous, $weight->fresh());
            $weight->update(['adg' => $adg]);
            $weight->refresh();
        }

        // Check on target
        $onTarget = $this->growth->checkOnTarget($animal, $weight->fresh());
        $weight->update(['on_target' => $onTarget]);
        $weight->refresh();

        return response()->json(['data' => new WeightResource($weight->load('animal'))], 201);
    }

    public function show(Weight $weight)
    {
        Gate::authorize('calves.view', $weight);

        return response()->json(['data' => new WeightResource($weight->load('animal'))]);
    }

    public function destroy(Weight $weight)
    {
        Gate::authorize('calves.delete', $weight);

        $weight->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /animals/{id}/growth — Weight series with benchmark curve.
     */
    public function growth(Request $request, Animal $animal)
    {
        Gate::authorize('calves.view', $animal);

        $series = $this->growth->weightSeries($animal);

        return response()->json(['data' => [
            'animal_id' => $animal->id,
            'ear_tag' => $animal->ear_tag,
            'birth_date' => $animal->birth_date?->toISOString(),
            'birth_weight' => $animal->calfRecord?->birth_weight,
            'overall_adg' => $this->growth->computeOverallAdg($animal),
            'series' => $series,
        ]]);
    }
}
