<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Alerts\Services\AlertService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AlertRuleStoreRequest;
use App\Http\Requests\AlertRuleUpdateRequest;
use App\Http\Resources\AlertRuleResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlertRulesController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('alerts.view');

        $alertRules = AlertRule::query()
            ->orderBy('name')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        return response()->json([
            'data' => AlertRuleResource::collection($alertRules),
            'meta' => [
                'page' => $alertRules->currentPage(),
                'per_page' => $alertRules->perPage(),
                'total' => $alertRules->total(),
                'last_page' => $alertRules->lastPage(),
            ],
        ]);
    }

    public function store(AlertRuleStoreRequest $request)
    {
        Gate::authorize('alerts.edit');

        $alertRule = AlertRule::create($request->validated());

        return response()->json(['data' => new AlertRuleResource($alertRule)], 201);
    }

    public function show(AlertRule $alertRule)
    {
        Gate::authorize('alerts.view', $alertRule);

        return response()->json(['data' => new AlertRuleResource($alertRule)]);
    }

    public function update(AlertRuleUpdateRequest $request, AlertRule $alertRule)
    {
        Gate::authorize('alerts.edit', $alertRule);

        $validated = $request->validated();
        if (empty($validated)) {
            // Empty PATCH body means dismiss/deactivate
            $alertRule->update(['is_active' => false]);
        } else {
            $alertRule->update($validated);
        }

        return response()->json(['data' => new AlertRuleResource($alertRule->fresh())]);
    }

    public function generate(Request $request, AlertService $alertService)
    {
        Gate::authorize('alerts.create');

        $created = $alertService->evaluate();

        return response()->json(['data' => ['created' => count($created)]]);
    }

    public function destroy(AlertRule $alertRule)
    {
        Gate::authorize('alerts.delete', $alertRule);

        $alertRule->delete();

        return response()->json(null, 204);
    }
}
