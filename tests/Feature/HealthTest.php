<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Support\Str;

function healthOwnerToken(): string
{
    $owner = User::where('email', 'owner@springwaterdairy.local')->first();

    return $owner->createToken('test')->plainTextToken;
}

function healthManagerToken(): string
{
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    return $manager->createToken('test')->plainTextToken;
}

function healthMilkerToken(): string
{
    $milker = User::factory()->create();
    $milker->assignRole('Milker');

    return $milker->createToken('test')->plainTextToken;
}

function healthVetToken(): string
{
    $vet = User::factory()->create();
    $vet->assignRole('Vet');

    return $vet->createToken('test')->plainTextToken;
}

test('owner can record a treatment', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->postJson('/api/v1/treatments', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'treatment_date' => now()->toDateString(),
            'condition_id' => $condition->id,
            'drug_name' => 'Mastitis Guard',
            'dose' => '10ml',
            'route' => 'IM',
            'milk_withholding_days' => 4,
            'meat_withholding_days' => 14,
        ]);

    $res->assertStatus(201);
    $data = $res->json('data');
    expect($data['animal_ear_tag'])->toBe($cow->ear_tag);
    expect($data['drug_name'])->toBe('Mastitis Guard');
    expect($data['milk_safe_date'])->toBe(now()->addDays(4)->toDateString().'T00:00:00.000000Z');
    expect($data['meat_safe_date'])->toBe(now()->addDays(14)->toDateString().'T00:00:00.000000Z');
});

test('milker can view health records', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.healthMilkerToken()])
        ->getJson('/api/v1/treatments')
        ->assertOk();

    $this->withHeaders(['Authorization' => 'Bearer '.healthMilkerToken()])
        ->getJson('/api/v1/vaccinations')
        ->assertOk();
});

test('milker cannot record a treatment (health.create intentionally omitted)', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    $this->withHeaders(['Authorization' => 'Bearer '.healthMilkerToken()])
        ->postJson('/api/v1/treatments', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'treatment_date' => now()->toDateString(),
            'condition_id' => $condition->id,
            'drug_name' => 'Mastitis Guard',
            'dose' => '10ml',
            'route' => 'IM',
            'milk_withholding_days' => 4,
            'meat_withholding_days' => 14,
        ])
        ->assertStatus(403);
});

test('viewer cannot record a treatment', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/treatments', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'treatment_date' => now()->toDateString(),
            'condition_id' => $condition->id,
            'drug_name' => 'Mastitis Guard',
            'dose' => '10ml',
            'route' => 'IM',
            'milk_withholding_days' => 4,
            'meat_withholding_days' => 14,
        ])
        ->assertStatus(403);
});

test('male animal cannot receive treatment', function () {
    $bull = Animal::factory()->male()->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->postJson('/api/v1/treatments', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $bull->id,
            'treatment_date' => now()->toDateString(),
            'condition_id' => Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first()->id,
            'drug_name' => 'Test Drug',
            'dose' => '10ml',
            'route' => 'IM',
            'milk_withholding_days' => 4,
            'meat_withholding_days' => 14,
        ]);

    $res->assertStatus(422);
});

test('treatment is idempotent via client_uuid', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $uuid = (string) Str::uuid();

    $first = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->postJson('/api/v1/treatments', [
            'client_uuid' => $uuid,
            'animal_id' => $cow->id,
            'treatment_date' => now()->toDateString(),
            'condition_id' => Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first()->id,
            'drug_name' => 'Test Drug',
            'dose' => '10ml',
            'route' => 'IM',
            'milk_withholding_days' => 4,
            'meat_withholding_days' => 14,
        ]);

    $second = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->postJson('/api/v1/treatments', [
            'client_uuid' => $uuid,
            'animal_id' => $cow->id,
            'treatment_date' => now()->toDateString(),
            'condition_id' => Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first()->id,
            'drug_name' => 'Different Drug',
            'dose' => '20ml',
            'route' => 'IV',
            'milk_withholding_days' => 7,
            'meat_withholding_days' => 21,
        ]);

    $firstId = $first->json('data.id');
    $secondId = $second->json('data.id');
    expect($secondId)->toBe($firstId);
    expect(Treatment::where('animal_id', $cow->id)->count())->toBe(1);
});

test('sick bay returns animals in withholding', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    Treatment::factory()->create([
        'animal_id' => $cow->id,
        'condition_id' => $condition->id,
        'drug_name' => 'Test Drug',
        'dose' => '10ml',
        'route' => 'IM',
        'milk_withholding_days' => 4,
        'meat_withholding_days' => 14,
        'milk_safe_date' => now()->addDays(4)->toDateString(),
        'meat_safe_date' => now()->addDays(14)->toDateString(),
    ]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->getJson('/api/v1/health/sick-bay');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($data[0]['ear_tag'])->toBe($cow->ear_tag);
    expect($data[0]['milk_safe_date'])->toBeString();
});

test('animal health endpoint returns treatments and vaccinations', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    Treatment::factory()->create([
        'animal_id' => $cow->id,
        'condition_id' => $condition->id,
        'drug_name' => 'Test Drug',
        'dose' => '10ml',
        'route' => 'IM',
        'milk_withholding_days' => 4,
        'meat_withholding_days' => 14,
        'milk_safe_date' => now()->addDays(4)->toDateString(),
        'meat_safe_date' => now()->addDays(14)->toDateString(),
    ]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->getJson('/api/v1/animals/'.$cow->id.'/health');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data['animal_id'])->toBe($cow->id);
    expect($data['in_withholding'])->toBe(true);
    expect($data['treatments'])->toHaveCount(1);
    expect($data['vaccinations'])->toHaveCount(0);
});

test('vaccination record create and list', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->postJson('/api/v1/vaccinations', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'condition_id' => $condition->id,
            'vaccination_date' => now()->toDateString(),
            'vaccine_name' => 'Rabies Vaccine',
            'dose' => '2ml',
            'route' => 'IM',
            'next_due_date' => now()->addDays(365)->toDateString(),
        ]);

    $res->assertStatus(201);
    expect($res->json('data.vaccine_name'))->toBe('Rabies Vaccine');
    expect($res->json('data.animal_ear_tag'))->toBe($cow->ear_tag);

    $listRes = $this->withHeaders(['Authorization' => 'Bearer '.healthOwnerToken()])
        ->getJson('/api/v1/vaccinations');

    $listRes->assertStatus(200);
    expect($listRes->json('meta.total'))->toBeGreaterThanOrEqual(1);
});

test('vet can record vaccinations', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    $this->withHeaders(['Authorization' => 'Bearer '.healthVetToken()])
        ->postJson('/api/v1/vaccinations', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'condition_id' => $condition->id,
            'vaccination_date' => now()->toDateString(),
            'vaccine_name' => 'Rabies Vaccine',
            'dose' => '2ml',
            'route' => 'IM',
        ])
        ->assertStatus(201);
});

test('milker cannot record vaccinations', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    $this->withHeaders(['Authorization' => 'Bearer '.healthMilkerToken()])
        ->postJson('/api/v1/vaccinations', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'condition_id' => $condition->id,
            'vaccination_date' => now()->toDateString(),
            'vaccine_name' => 'Rabies Vaccine',
            'dose' => '2ml',
            'route' => 'IM',
        ])
        ->assertStatus(403);
});
