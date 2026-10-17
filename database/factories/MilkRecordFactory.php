<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Milk\Models\MilkRecord;
use App\Domain\Animals\Models\Animal;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Milk\Models\MilkRecord>
 */
class MilkRecordFactory extends Factory
{
    protected $model = MilkRecord::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'animal_id' => Animal::factory(),
            'record_date' => fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'session' => fake()->randomElement(['morning', 'evening']),
            'litres' => fake()->randomFloat(2, 5, 40),
            'cmt_score' => fake()->optional()->numberBetween(0, 5),
            'scc' => fake()->optional()->numberBetween(10, 500),
            'in_withholding' => false,
            'excluded_from_sales' => false,
            'version' => 1,
        ];
    }

    public function withholding(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_withholding' => true,
            'excluded_from_sales' => true,
        ]);
    }

    public function forDate(string $date, string $session = 'morning'): static
    {
        return $this->state(fn (array $attributes) => [
            'record_date' => $date,
            'session' => $session,
        ]);
    }
}
