<?php

namespace Database\Factories;

use App\Domain\Animals\Models\Animal;
use App\Domain\Images\Models\Image;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        return [
            'imageable_type' => Animal::class,
            'imageable_id' => Animal::factory(),
            'path' => 'images/animals/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'is_primary' => false,
            'uploaded_by' => null,
        ];
    }
}
