<?php

namespace Database\Seeders;

use App\Domain\Tenancy\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, description: string, price_monthly: int, limits: array<string, int|null>, is_active: bool, sort_order: int}>
     */
    private array $plans = [
        [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'For small farms getting started with digital records.',
            'price_monthly' => 0,
            'limits' => [
                'animals' => 50,
                'users' => 3,
                'milk_records' => 3000,
                'milk_records_per_month' => 3000,
                'alert_rules' => 5,
            ],
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'code' => 'growth',
            'name' => 'Growth',
            'description' => 'For growing herds with more people and records.',
            'price_monthly' => 7900,
            'limits' => [
                'animals' => 500,
                'users' => 25,
                'milk_records' => 50000,
                'milk_records_per_month' => 50000,
                'alert_rules' => 50,
            ],
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'code' => 'scale',
            'name' => 'Scale',
            'description' => 'Unlimited records for large commercial operations.',
            'price_monthly' => 24900,
            'limits' => [
                'animals' => null,
                'users' => null,
                'milk_records' => null,
                'milk_records_per_month' => null,
                'alert_rules' => null,
            ],
            'is_active' => true,
            'sort_order' => 3,
        ],
    ];

    public function run(): void
    {
        foreach ($this->plans as $plan) {
            Plan::firstOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
