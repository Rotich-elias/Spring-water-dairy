<?php

namespace App\Domain\Tenancy\Database\Factories;

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
            'ends_at' => null,
        ];
    }
}
