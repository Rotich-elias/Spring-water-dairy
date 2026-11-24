<?php

namespace App\Domain\Billing\Database\Factories;

use App\Domain\Billing\Models\PlanChangeRequest;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanChangeRequest>
 */
class PlanChangeRequestFactory extends Factory
{
    protected $model = PlanChangeRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::query()->first() ?? Tenant::factory()->create();
        $plan = Plan::query()->where('code', 'growth')->first()
            ?? Plan::factory()->create(['code' => 'growth', 'price_monthly' => 79]);
        $currentPlan = Plan::query()->where('code', 'starter')->first();

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'plan_name' => $plan->name,
            'current_plan_id' => $currentPlan?->id,
            'current_plan_code' => $currentPlan?->code,
            'current_plan_name' => $currentPlan?->name,
            'status' => PlanChangeRequest::STATUS_PENDING,
            'amount_minor' => (int) round(((float) $plan->price_monthly) * 100),
            'currency' => 'KES',
        ];
    }
}
