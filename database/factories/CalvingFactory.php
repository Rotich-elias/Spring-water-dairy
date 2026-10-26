<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Calving;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CalvingFactory extends Factory
{
    protected $model = Calving::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'dam_id' => Animal::factory()->female(),
            'calving_date' => fake()->dateTimeBetween('-60 days', 'today'),
            'ease_score_id' => Lookup::where('type', 'calving-ease-scores')->where('active', true)->inRandomOrder()->first()?->id,
            'retained_placenta' => fake()->boolean(10),
            'notes' => fake()->optional()->sentence(),
            'version' => 1,
        ];
    }
}
