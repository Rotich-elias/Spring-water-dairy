<?php

namespace Database\Factories;

use App\Domain\Alerts\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['health', 'breeding', 'milk', 'system', 'alert']),
            'title' => fake()->sentence(),
            'message' => fake()->sentence(),
            'data' => null,
            'read_at' => fake()->optional()->dateTime(),
        ];
    }
}
