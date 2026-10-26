<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Health\Models\Vaccination;
use App\Http\Controllers\Controller;
use App\Http\Requests\VaccinationStoreRequest;
use App\Http\Resources\VaccinationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VaccinationsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('health.view');

        $query = Vaccination::query()->with(['animal', 'condition', 'pen']);

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('pen_id')) {
            $query->where('pen_id', $request->pen_id);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('vaccination_date', [$request->from, $request->to]);
        }

        $vaccinations = $query->orderByDesc('vaccination_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => VaccinationResource::collection($vaccinations),
            'meta' => [
                'page' => $vaccinations->currentPage(),
                'per_page' => $vaccinations->perPage(),
                'total' => $vaccinations->total(),
                'last_page' => $vaccinations->lastPage(),
            ],
        ]);
    }

    public function store(VaccinationStoreRequest $request)
    {
        Gate::authorize('health.create');

        $vaccination = Vaccination::create($request->validated());

        return response()->json(['data' => new VaccinationResource($vaccination->load(['animal', 'condition', 'pen']))], 201);
    }

    public function show(Vaccination $vaccination)
    {
        Gate::authorize('health.view', $vaccination);

        return response()->json(['data' => new VaccinationResource($vaccination->load(['animal', 'condition', 'pen']))]);
    }

    public function destroy(Vaccination $vaccination)
    {
        Gate::authorize('health.delete', $vaccination);

        $vaccination->delete();

        return response()->json(null, 204);
    }
}
