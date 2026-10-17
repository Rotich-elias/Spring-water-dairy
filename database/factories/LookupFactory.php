<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Lookup;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lookup>
 */
class LookupFactory extends Factory
{
    protected $model = Lookup::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([
                'categories', 'breeds', 'pens', 'animal-statuses',
                'pd-results', 'calving-ease-scores', 'health-conditions', 'feed-groups',
            ]),
            'name' => fake()->unique()->word(),
            'active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
