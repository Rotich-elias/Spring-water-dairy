<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Animal>
 */
class AnimalFactory extends Factory
{
    protected $model = Animal::class;

    public function definition(): array
    {
        $category = Lookup::where('type', 'categories')->where('active', true)->inRandomOrder()->first()?->id;
        $pen = Lookup::where('type', 'pens')->where('active', true)->inRandomOrder()->first()?->id;
        $status = Lookup::where('type', 'animal-statuses')->where('active', true)->inRandomOrder()->first()?->id;
        $breed = Lookup::where('type', 'breeds')->where('active', true)->inRandomOrder()->first()?->id;

        return [
            'client_uuid' => (string) Str::uuid(),
            'ear_tag' => 'COW-'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->optional()->firstName(),
            'category_id' => $category,
            'pen_id' => $pen,
            'status_id' => $status,
            'breed_id' => $breed,
            'sex' => fake()->randomElement(['M', 'F']),
            'birth_date' => fake()->dateTimeBetween('-5 years', '-6 months')->format('Y-m-d'),
            'version' => 1,
        ];
    }

    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 'M',
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 'F',
        ]);
    }

    public function ofCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    public function ofStatus(int $statusId): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $statusId,
        ]);
    }

    public function ofPen(int $penId): static
    {
        return $this->state(fn (array $attributes) => [
            'pen_id' => $penId,
        ]);
    }

    public function ofBreed(int $breedId): static
    {
        return $this->state(fn (array $attributes) => [
            'breed_id' => $breedId,
        ]);
    }
}
