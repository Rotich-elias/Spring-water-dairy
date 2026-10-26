<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Breeding\Models\Service;
use App\Domain\Breeding\Services\BreedingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceStoreRequest;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServicesController extends Controller
{
    public function __construct(private BreedingService $breeding) {}

    public function index(Request $request)
    {
        Gate::authorize('breeding.view');

        $query = Service::query()->with(['animal', 'bull']);

        if ($request->filled('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('service_date', [$request->from, $request->to]);
        }

        $services = $query->orderByDesc('service_date')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => ServiceResource::collection($services),
            'meta' => [
                'page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
            ],
        ]);
    }

    public function store(ServiceStoreRequest $request)
    {
        $service = $this->breeding->recordService($request->validated());

        return response()->json(['data' => new ServiceResource($service->load(['animal', 'bull']))], 201);
    }

    public function show(Service $service)
    {
        Gate::authorize('breeding.view', $service);

        return response()->json(['data' => new ServiceResource($service->load(['animal', 'bull']))]);
    }

    public function update(Request $request, Service $service)
    {
        Gate::authorize('breeding.update', $service);

        $service->update($request->validate([
            'inseminator' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_type' => ['sometimes', 'required', 'in:AI,Natural'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]));
        $service->increment('version');

        return response()->json(['data' => new ServiceResource($service->fresh()->load(['animal', 'bull']))]);
    }

    public function destroy(Service $service)
    {
        Gate::authorize('breeding.delete', $service);

        $service->delete();

        return response()->json(null, 204);
    }
}
