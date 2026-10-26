<?php

namespace Database\Factories;

use App\Domain\Breeding\Models\SemenStraw;
use Illuminate\Database\Eloquent\Factories\Factory;

class SemenStrawFactory extends Factory
{
    protected $model = SemenStraw::class;

    public function definition(): array
    {
        return [
            'bull_code' => 'BULL-'.fake()->unique()->numberBetween(1, 999),
            'bull_name' => fake()->company(),
            'breed' => fake()->randomElement(['Friesian', 'Jersey', 'Ayrshire']),
            'quantity' => fake()->numberBetween(1, 100),
            'unit_price' => fake()->randomFloat(2, 20, 100),
            'expiry_date' => fake()->optional()->dateTimeBetween('+6 months', '+2 years')?->format('Y-m-d'),
        ];
    }
}
