<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use App\Domain\Milk\Models\MilkRecord;
use App\Models\Lookup;
use App\Models\User;
use Carbon\Carbon;

test('owner can list available reports', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);
    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain('animal-register');
    expect($ids)->toContain('breeding-pregnancy');
    expect($ids)->toContain('calving');
    expect($ids)->toContain('milk-production');
    expect($ids)->toContain('health-treatment');
    expect($ids)->toContain('mortality');
    foreach ($data as $report) {
        expect($report)->toHaveKey('id');
        expect($report)->toHaveKey('name');
        expect($report)->toHaveKey('description');
        expect($report)->toHaveKey('type');
    }
});

test('owner can generate animal register report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/animal-register');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('owner can generate milk production report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/milk-production', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]);

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('owner can generate mortality report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/mortality', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]);

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('unknown report type returns empty data', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/unknown-type');

    $res->assertStatus(200);
    expect($res->json('data'))->toBe([]);
});

test('milk production report excludes milk withheld from sales', function () {
    $token = $this->authenticateAsOwner();

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $withheldCow = Animal::factory()->female()->ofCategory($category->id)->create();

    MilkRecord::factory()->forDate('2026-10-10')->create([
        'animal_id' => $cow->id,
        'litres' => 10,
    ]);
    MilkRecord::factory()->forDate('2026-10-10', 'evening')->create([
        'animal_id' => $cow->id,
        'litres' => 5,
    ]);
    MilkRecord::factory()->forDate('2026-10-10')->withholding()->create([
        'animal_id' => $withheldCow->id,
        'litres' => 20,
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/milk-production?from=2026-10-01&to=2026-10-18');

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.date'))->toBe('2026-10-10');
    expect($res->json('data.0.total_litres'))->toEqual(15);
    expect($res->json('data.0.cows'))->toBe(1);
    expect($res->json('data.0.records'))->toBe(2);
});

test('health report filters treatments by date and reports withholding', function () {
    $token = $this->authenticateAsOwner();

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    Treatment::factory()->create([
        'animal_id' => $cow->id,
        'condition_id' => $condition->id,
        'treatment_date' => '2026-10-10',
        'drug_name' => 'Mastitis Guard',
        'milk_safe_date' => Carbon::today()->addDays(3)->toDateString(),
        'meat_safe_date' => Carbon::today()->addDays(10)->toDateString(),
    ]);

    Treatment::factory()->create([
        'animal_id' => $cow->id,
        'condition_id' => $condition->id,
        'treatment_date' => '2026-09-01',
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/health-treatment?from=2026-10-01&to=2026-10-18');

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.animal_ear_tag'))->toBe($cow->ear_tag);
    expect($res->json('data.0.condition'))->toBe('Mastitis');
    expect($res->json('data.0.in_withholding'))->toBeTrue();
});

test('non-reports user cannot list reports', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports')
        ->assertStatus(403);
});
