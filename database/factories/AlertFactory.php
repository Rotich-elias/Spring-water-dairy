<?php

namespace Database\Factories;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Animals\Models\Animal;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['withholding', 'overdue_calving', 'missing_pd_check', 'vaccine_due']),
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'animal_id' => Animal::factory(),
            'read_at' => null,
            'dismissed_at' => null,
        ];
    }
}
