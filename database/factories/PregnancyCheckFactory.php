<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PregnancyCheckFactory extends Factory
{
    protected $model = PregnancyCheck::class;

    public function definition(): array
    {
        $results = ['pregnant', 'open', 'recheck'];

        return [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => Animal::factory()->female(),
            'check_date' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'pd_result_id' => Lookup::where('type', 'pd-results')->where('active', true)->inRandomOrder()->first()?->id,
            'result' => fake()->randomElement($results),
            'notes' => fake()->optional()->sentence(),
            'version' => 1,
        ];
    }
}
