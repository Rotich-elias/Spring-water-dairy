<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Domain\Milk\Models\BulkTankRecord;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Milk\Models\BulkTankRecord>
 */
class BulkTankRecordFactory extends Factory
{
    protected $model = BulkTankRecord::class;

    public function definition(): array
    {
        return [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'record_date' => fake()->dateTimeBetween('-30 days', 'today')->format('Y-m-d'),
            'session' => fake()->randomElement(['morning', 'evening']),
            'litres_collected' => fake()->randomFloat(2, 200, 800),
            'litres_sold' => fake()->randomFloat(2, 150, 700),
            'litres_discarded' => fake()->randomFloat(2, 0, 50),
            'buyer' => fake()->company(),
            'price_per_litre' => fake()->randomFloat(2, 30, 60),
            'version' => 1,
        ];
    }
}
