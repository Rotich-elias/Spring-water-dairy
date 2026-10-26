<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Calves\Models\CalfRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalfRecordFactory extends Factory
{
    protected $model = CalfRecord::class;

    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'colostrum_litres' => fake()->optional()->randomFloat(2, 1, 5),
            'colostrum_hours' => fake()->optional()->numberBetween(1, 12),
            'navel_dip' => fake()->boolean(),
            'disbudding' => fake()->boolean(),
            'weaning_date' => fake()->optional()->dateTimeBetween('-30 days', 'today')?->format('Y-m-d'),
            'weaning_weight' => fake()->optional()->randomFloat(2, 30, 100),
            'birth_weight' => fake()->randomFloat(2, 25, 50),
            'outcome' => 'live',
        ];
    }
}
