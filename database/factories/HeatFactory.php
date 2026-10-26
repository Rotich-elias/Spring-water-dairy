<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Heat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HeatFactory extends Factory
{
    protected $model = Heat::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => Animal::factory()->female(),
            'heat_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'observed_by' => fake()->name(),
            'notes' => fake()->optional()->sentence(),
            'version' => 1,
        ];
    }
}
