<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Images\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function imageOwnerHeaders(): array
{
    $owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();

    return ['Authorization' => 'Bearer '.$owner->createToken('test')->plainTextToken];
}

function uploadAnimalImage($test, Animal $animal, array $overrides = [])
{
    return $test->withHeaders(imageOwnerHeaders())->post('/api/v1/images', array_merge([
        'imageable_type' => 'animal',
        'imageable_id' => $animal->id,
        'image' => UploadedFile::fake()->image('cow.jpg', 800, 600),
    ], $overrides));
}

test('owner can upload an image for an animal', function () {
    $animal = Animal::factory()->create();

    $response = uploadAnimalImage($this, $animal);

    $response->assertCreated()
        ->assertJsonPath('data.imageable_type', 'animal')
        ->assertJsonPath('data.imageable_id', $animal->id)
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonStructure([
            'data' => ['id', 'url', 'original_name', 'mime_type', 'size', 'is_primary', 'created_at'],
        ]);

    $image = Image::firstOrFail();

    Storage::disk('public')->assertExists($image->path);
    expect($image->url)->toContain($image->path);
});

test('the first image is primary and a new primary demotes the old one', function () {
    $animal = Animal::factory()->create();

    $first = uploadAnimalImage($this, $animal)->json('data');
    $second = uploadAnimalImage($this, $animal, ['is_primary' => true])->json('data');

    expect(Image::find($first['id'])->is_primary)->toBeFalse()
        ->and(Image::find($second['id'])->is_primary)->toBeTrue()
        ->and(Image::where('is_primary', true)->count())->toBe(1);
});

test('an image can be promoted to primary', function () {
    $animal = Animal::factory()->create();

    $first = uploadAnimalImage($this, $animal)->json('data');
    $second = uploadAnimalImage($this, $animal)->json('data');

    $this->withHeaders(imageOwnerHeaders())
        ->patchJson('/api/v1/images/'.$first['id'], ['is_primary' => true])
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect(Image::find($first['id'])->is_primary)->toBeTrue()
        ->and(Image::find($second['id'])->is_primary)->toBeFalse();
});

test('unsetting the only primary image keeps it primary', function () {
    $animal = Animal::factory()->create();

    $image = uploadAnimalImage($this, $animal)->json('data');

    $this->withHeaders(imageOwnerHeaders())
        ->patchJson('/api/v1/images/'.$image['id'], ['is_primary' => false])
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);
});

test('deleting the primary image promotes the next image and removes the file', function () {
    $animal = Animal::factory()->create();

    $first = uploadAnimalImage($this, $animal)->json('data');
    $second = uploadAnimalImage($this, $animal)->json('data');
    $firstModel = Image::findOrFail($first['id']);

    $this->withHeaders(imageOwnerHeaders())
        ->deleteJson('/api/v1/images/'.$first['id'])
        ->assertNoContent();

    $this->assertDatabaseMissing('images', ['id' => $first['id']]);
    Storage::disk('public')->assertMissing($firstModel->path);
    expect(Image::find($second['id'])->is_primary)->toBeTrue();
});

test('images can be listed for a record and are included in the animal profile', function () {
    $animal = Animal::factory()->create();
    uploadAnimalImage($this, $animal);

    $headers = imageOwnerHeaders();

    $this->withHeaders($headers)
        ->getJson('/api/v1/images?imageable_type=animal&imageable_id='.$animal->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.imageable_id', $animal->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/animals/'.$animal->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.images')
        ->assertJsonPath('data.images.0.is_primary', true);
});

test('users with view-only animal permission cannot upload but can list', function () {
    $animal = Animal::factory()->create();
    Image::factory()->for($animal, 'imageable')->create(['is_primary' => true]);

    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $headers = ['Authorization' => 'Bearer '.$milker->createToken('test')->plainTextToken];

    $this->withHeaders($headers)
        ->getJson('/api/v1/images?imageable_type=animal&imageable_id='.$animal->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeaders($headers)
        ->post('/api/v1/images', [
            'imageable_type' => 'animal',
            'imageable_id' => $animal->id,
            'image' => UploadedFile::fake()->image('cow.jpg'),
        ])
        ->assertForbidden();
});

test('users with view-only animal permission cannot delete images', function () {
    $animal = Animal::factory()->create();
    $image = Image::factory()->for($animal, 'imageable')->create(['is_primary' => true]);

    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $headers = ['Authorization' => 'Bearer '.$milker->createToken('test')->plainTextToken];

    $this->withHeaders($headers)
        ->deleteJson('/api/v1/images/'.$image->id)
        ->assertForbidden();
});

test('image upload validates the record type, record existence and file type', function () {
    $animal = Animal::factory()->create();
    $headers = imageOwnerHeaders();

    $this->withHeaders($headers)
        ->post('/api/v1/images', [
            'imageable_type' => 'unicorn',
            'imageable_id' => $animal->id,
            'image' => UploadedFile::fake()->image('cow.jpg'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('imageable_type', 'error.fields');

    $this->withHeaders($headers)
        ->post('/api/v1/images', [
            'imageable_type' => 'animal',
            'imageable_id' => 999999,
            'image' => UploadedFile::fake()->image('cow.jpg'),
        ])
        ->assertNotFound();

    $this->withHeaders($headers)
        ->post('/api/v1/images', [
            'imageable_type' => 'animal',
            'imageable_id' => $animal->id,
            'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image', 'error.fields');
});
