<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Milk\Models\Lactation;
use App\Domain\Animals\Models\Animal;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Milk\Models\Lactation>
 */
class LactationFactory extends Factory
{
    protected $model = Lactation::class;

    public function definition(): array
    {
        $started = fake()->dateTimeBetween('-2 years', '-3 months')->format('Y-m-d');

        return [
            'animal_id' => Animal::factory()->female(),
            'lactation_number' => fake()->numberBetween(1, 6),
            'started_at' => $started,
            'ended_at' => fake()->optional()->dateTimeBetween($started, 'today')?->format('Y-m-d'),
            'total_litres' => fake()->randomFloat(2, 1000, 10000),
            'peak_yield' => fake()->randomFloat(2, 20, 50),
            'peak_day' => fake()->numberBetween(30, 120),
            'drying_off_date' => fake()->optional()->dateTimeBetween($started, 'today')?->format('Y-m-d'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
            'drying_off_date' => null,
        ]);
    }
}
