<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Breeding\Models\Service;
use App\Domain\Animals\Models\Animal;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $serviceDate = fake()->dateTimeBetween('-120 days', 'today')->format('Y-m-d');

        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'animal_id' => Animal::factory()->female(),
            'service_date' => $serviceDate,
            'bull_id' => null,
            'inseminator' => fake()->name(),
            'service_type' => 'AI',
            'expected_calving_date' => fake()->dateTimeBetween($serviceDate, '+300 days')->format('Y-m-d'),
            'target_dry_date' => fake()->dateTimeBetween($serviceDate, '+200 days')->format('Y-m-d'),
            'pd_window_start' => fake()->dateTimeBetween($serviceDate, '+100 days')->format('Y-m-d'),
            'pd_window_end' => fake()->dateTimeBetween($serviceDate, '+120 days')->format('Y-m-d'),
            'reproductive_status' => 'open',
            'version' => 1,
        ];
    }
}
