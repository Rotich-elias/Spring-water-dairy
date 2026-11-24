<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use Illuminate\Support\Facades\DB;

class PlansController extends Controller
{
    public function index()
    {
        $plans = Plan::active()->ordered()->get();
        $currentPlanCode = $this->currentPlanCode();

        $plans->each(function (Plan $plan) use ($currentPlanCode): void {
            $plan->setAttribute('current', $plan->code === $currentPlanCode);
        });

        return response()->json(['data' => PlanResource::collection($plans)]);
    }

    private function currentPlanCode(): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $tenantId = $user->current_tenant_id
            ?? DB::table('tenant_user')->where('user_id', $user->id)->orderBy('tenant_id')->value('tenant_id');

        if ($tenantId === null) {
            return null;
        }

        return DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.tenant_id', $tenantId)
            ->whereIn('subscriptions.status', ['active', 'trialing'])
            ->orderByDesc('subscriptions.id')
            ->value('plans.code');
    }
}
