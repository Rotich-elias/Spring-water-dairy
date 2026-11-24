<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PlanService;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlansController extends Controller
{
    public function __construct(private PlanService $plans, private TenantContext $context) {}

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

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return null;
        }

        $previous = $this->context->tenant();
        $this->context->set($tenant);

        try {
            // Use the same effective-plan rules as the billing endpoint so
            // expired trials and scheduled cancellations cannot disagree.
            return $this->plans->plan()->code;
        } catch (Throwable) {
            return null;
        } finally {
            if ($previous) {
                $this->context->set($previous);
            } else {
                $this->context->clear();
            }
        }
    }
}
