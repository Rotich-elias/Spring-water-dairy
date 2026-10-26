<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TreatmentFactory extends Factory
{
    protected $model = Treatment::class;

    public function definition(): array
    {
        $treatmentDate = fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d');
        $milkWithholding = fake()->numberBetween(0, 7);
        $meatWithholding = fake()->numberBetween(0, 30);

        return [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => Animal::factory(),
            'treatment_date' => $treatmentDate,
            'condition_id' => Lookup::where('type', 'health-conditions')->where('active', true)->inRandomOrder()->first()?->id,
            'drug_name' => fake()->word(),
            'dose' => fake()->numberBetween(1, 10).'ml',
            'route' => fake()->randomElement(['IM', 'IV', 'SC', 'Oral']),
            'milk_withholding_days' => $milkWithholding,
            'meat_withholding_days' => $meatWithholding,
            'milk_safe_date' => $milkWithholding > 0 ? fake()->dateTimeBetween('today', '+7 days')->format('Y-m-d') : null,
            'meat_safe_date' => $meatWithholding > 0 ? fake()->dateTimeBetween('today', '+30 days')->format('Y-m-d') : null,
            'version' => 1,
        ];
    }
}
