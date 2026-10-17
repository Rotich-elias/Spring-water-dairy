<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Animals\Models\Animal;

class HeatFactory extends Factory
{
    protected $model = Heat::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'animal_id' => Animal::factory()->female(),
            'heat_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'observed_by' => fake()->name(),
            'notes' => fake()->optional()->sentence(),
            'version' => 1,
        ];
    }
}
