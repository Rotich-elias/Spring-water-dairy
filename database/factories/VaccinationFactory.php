<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Vaccination;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VaccinationFactory extends Factory
{
    protected $model = Vaccination::class;

    public function definition(): array
    {
        $vaccinationDate = fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d');

        return [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => Animal::factory(),
            'condition_id' => Lookup::where('type', 'health-conditions')->where('active', true)->inRandomOrder()->first()?->id,
            'vaccination_date' => $vaccinationDate,
            'vaccine_name' => fake()->word().' Vaccine',
            'dose' => fake()->numberBetween(1, 5).'ml',
            'route' => fake()->randomElement(['IM', 'SC']),
            'pen_id' => null,
            'next_due_date' => fake()->dateTimeBetween('+180 days', '+365 days')->format('Y-m-d'),
            'version' => 1,
        ];
    }
}
