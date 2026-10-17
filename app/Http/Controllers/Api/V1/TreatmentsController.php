<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TreatmentStoreRequest;
use App\Http\Requests\TreatmentUpdateRequest;
use App\Http\Resources\TreatmentResource;
use App\Http\Resources\VaccinationResource;
use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use App\Domain\Health\Services\TreatmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TreatmentsController extends Controller
{
    public function __construct(private TreatmentService $treatmentService) {}

    public function index(Request $request)
    {
        Gate::authorize('health.view');

        $query = Treatment::query()->with(['animal', 'condition']);

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('treatment_date', [$request->from, $request->to]);
        }
        if ($request->filled('in_withholding') && $request->boolean('in_withholding')) {
            $query->whereNotNull('milk_safe_date')
                ->where('milk_safe_date', '>=', today())
                ->orWhere(function ($q) {
                    $q->whereNotNull('meat_safe_date')
                      ->where('meat_safe_date', '>=', today());
                });
        }

        $treatments = $query->orderByDesc('treatment_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => TreatmentResource::collection($treatments),
            'meta' => [
                'page' => $treatments->currentPage(),
                'per_page' => $treatments->perPage(),
                'total' => $treatments->total(),
                'last_page' => $treatments->lastPage(),
            ],
        ]);
    }

    public function store(TreatmentStoreRequest $request)
    {
        $treatment = $this->treatmentService->recordTreatment($request->validated());

        return response()->json(['data' => new TreatmentResource($treatment->load(['animal', 'condition']))], 201);
    }

    public function show(Treatment $treatment)
    {
        Gate::authorize('health.view', $treatment);

        return response()->json(['data' => new TreatmentResource($treatment->load(['animal', 'condition']))]);
    }

    public function update(TreatmentUpdateRequest $request, Treatment $treatment)
    {
        Gate::authorize('health.update', $treatment);

        $validated = $request->validated();
        $withholdingService = new \App\Domain\Health\Services\WithholdingService();

        if (isset($validated['milk_withholding_days']) || isset($validated['meat_withholding_days'])) {
            $safeDates = $withholdingService->computeSafeDates(
                $treatment->treatment_date,
                $validated['milk_withholding_days'] ?? $treatment->milk_withholding_days,
                $validated['meat_withholding_days'] ?? $treatment->meat_withholding_days
            );
            $validated = array_merge($validated, $safeDates);
        }

        $treatment->update($validated);
        $treatment->increment('version');

        return response()->json(['data' => new TreatmentResource($treatment->fresh()->load(['animal', 'condition']))]);
    }

    public function destroy(Treatment $treatment)
    {
        Gate::authorize('health.delete', $treatment);

        $treatment->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /health/sick-bay — Animals under treatment or withholding.
     */
    public function sickBay(Request $request)
    {
        Gate::authorize('health.view');

        $service = new \App\Domain\Health\Services\WithholdingService();
        $sickBay = $service->sickBay();

        return response()->json(['data' => $sickBay]);
    }

    /**
     * GET /animals/{id}/health — Full health history for an animal.
     */
    public function animalHealth(Request $request, Animal $animal)
    {
        Gate::authorize('health.view', $animal);

        $treatments = $animal->treatments()
            ->with('condition')
            ->orderByDesc('treatment_date')
            ->get();

        $vaccinations = $animal->vaccinations()
            ->with('condition')
            ->orderByDesc('vaccination_date')
            ->get();

        return response()->json(['data' => [
            'animal_id' => $animal->id,
            'ear_tag' => $animal->ear_tag,
            'in_withholding' => $animal->in_withholding,
            'treatments' => TreatmentResource::collection($treatments),
            'vaccinations' => VaccinationResource::collection($vaccinations),
        ]]);
    }
}
