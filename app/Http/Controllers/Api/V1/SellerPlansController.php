<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanStoreRequest;
use App\Http\Requests\PlanUpdateRequest;
use App\Http\Resources\PlanResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SellerPlansController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): JsonResponse
    {
        $this->authorizePlatform('plans.view');

        return response()->json([
            'data' => PlanResource::collection(Plan::query()->ordered()->get()),
        ]);
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorizePlatform('plans.view');

        return response()->json(['data' => new PlanResource($plan)]);
    }

    public function store(PlanStoreRequest $request): JsonResponse
    {
        $this->authorizePlatform('plans.create');

        $data = $request->validated();
        $plan = Plan::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_monthly' => $data['price_monthly'] ?? 0,
            'limits' => $data['limits'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => new PlanResource($plan)], 201);
    }

    public function update(PlanUpdateRequest $request, Plan $plan): JsonResponse
    {
        $this->authorizePlatform('plans.update');

        $data = $request->validated();

        if (($data['is_active'] ?? null) === false) {
            $this->guardDeactivation($plan);
        }

        $plan->fill($data);
        $plan->save();

        return response()->json(['data' => new PlanResource($plan)]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorizePlatform('plans.delete');
        $this->guardDeactivation($plan);

        // Historical subscriptions must still resolve their plan. Remove the
        // plan only when nothing references it.
        if ($this->hasSubscribers($plan)) {
            $plan->is_active = false;
            $plan->save();

            return response()->json([
                'data' => new PlanResource($plan),
                'deactivated' => true,
            ]);
        }

        $plan->delete();

        return response()->json([
            'data' => new PlanResource($plan),
            'deactivated' => false,
        ]);
    }

    private function guardDeactivation(Plan $plan): void
    {
        if ($plan->code === 'starter') {
            throw ValidationException::withMessages([
                'is_active' => 'The fallback Starter plan cannot be deactivated or removed.',
            ]);
        }

        if ($this->hasActiveSubscribers($plan)) {
            throw ValidationException::withMessages([
                'is_active' => 'Move this plan\'s active subscribers to another plan before deactivating or removing it.',
            ]);
        }
    }

    private function hasActiveSubscribers(Plan $plan): bool
    {
        return Subscription::query()
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }

    private function hasSubscribers(Plan $plan): bool
    {
        return Subscription::query()->where('plan_id', $plan->id)->exists();
    }

    private function authorizePlatform(string $ability): void
    {
        $this->context->clear();
        $this->authorize('platform.access');
        $this->authorize($ability);
    }
}
