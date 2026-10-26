<?php

namespace Database\Factories;

use App\Domain\Alerts\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement(['breeding', 'health', 'milk', 'general']),
            'due_date' => fake()->optional()->dateTimeBetween('today', '+30 days')?->format('Y-m-d'),
            'is_completed' => false,
        ];
    }
}
