<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Calves\Models\Weight;
use App\Domain\Animals\Models\Animal;

class WeightFactory extends Factory
{
    protected $model = Weight::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'animal_id' => Animal::factory(),
            'weight_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(2, 20, 500),
            'adg' => fake()->optional()->randomFloat(3, 0.1, 2.0),
            'on_target' => fake()->boolean(70),
            'version' => 1,
        ];
    }
}
