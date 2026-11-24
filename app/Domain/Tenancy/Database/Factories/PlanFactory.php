<?php

namespace App\Domain\Tenancy\Database\Factories;

use App\Domain\Tenancy\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price_monthly' => fake()->numberBetween(0, 50000),
            'limits' => ['animals' => 50, 'users' => 3],
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
