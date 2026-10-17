<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;

class PregnancyCheckFactory extends Factory
{
    protected $model = PregnancyCheck::class;

    public function definition(): array
    {
        $results = ['pregnant', 'open', 'recheck'];

        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'animal_id' => Animal::factory()->female(),
            'check_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'pd_result_id' => Lookup::where('type', 'pd-results')->where('active', true)->inRandomOrder()->first()?->id,
            'result' => fake()->randomElement($results),
            'notes' => fake()->optional()->sentence(),
            'version' => 1,
        ];
    }
}
