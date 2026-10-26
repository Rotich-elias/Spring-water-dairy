<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Calves\Models\Weight;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WeightFactory extends Factory
{
    protected $model = Weight::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => Animal::factory(),
            'weight_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(2, 20, 500),
            'adg' => fake()->optional()->randomFloat(3, 0.1, 2.0),
            'on_target' => fake()->boolean(70),
            'version' => 1,
        ];
    }
}
