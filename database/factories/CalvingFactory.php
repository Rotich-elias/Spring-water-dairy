<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;

class CalvingFactory extends Factory
{
    protected $model = Calving::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'dam_id' => Animal::factory()->female(),
            'calving_date' => fake()->dateTimeBetween('-60 days', 'today'),
            'ease_score_id' => Lookup::where('type', 'calving-ease-scores')->where('active', true)->inRandomOrder()->first()?->id,
            'retained_placenta' => fake()->boolean(10),
            'notes' => fake()->optional()->sentence(),
            'version' => 1,
        ];
    }
}
