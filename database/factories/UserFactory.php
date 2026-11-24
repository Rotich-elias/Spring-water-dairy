<?php

namespace Database\Factories;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'password' => bcrypt('password'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $tenant = Tenant::query()
                ->where('slug', config('plans.default_tenant.slug', 'spring-water-dairy'))
                ->first();

            if (! $tenant) {
                return;
            }

            $role = $user->role;

            $tenant->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => filled($role) ? (string) $role : null,
                    'is_owner' => $role === 'Owner',
                ],
            ]);

            $user->forceFill(['current_tenant_id' => $tenant->id])->save();
        });
    }
}
