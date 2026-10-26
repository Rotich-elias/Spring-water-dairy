<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Breeding\Models\Calving;
use App\Domain\Breeding\Models\Heat;
use App\Domain\Breeding\Models\PregnancyCheck;
use App\Domain\Breeding\Models\SemenStraw;
use App\Domain\Breeding\Models\Service;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Support\Str;

function breedingOwnerToken(): string
{
    $owner = User::where('email', 'owner@springwaterdairy.local')->first();

    return $owner->createToken('test')->plainTextToken;
}

function breedingMilkerToken(): string
{
    $milker = User::factory()->create();
    $milker->assignRole('Milker');

    return $milker->createToken('test')->plainTextToken;
}

function breedingVetToken(): string
{
    $vet = User::factory()->create();
    $vet->assignRole('Vet');

    return $vet->createToken('test')->plainTextToken;
}

test('owner can record a heat', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/heats', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'heat_date' => now()->toDateString(),
            'observed_by' => 'Tester',
            'notes' => 'Standing heat',
        ]);

    $res->assertStatus(201);
    expect($res->json('data.animal_ear_tag'))->toBe($cow->ear_tag);
    expect(Heat::where('animal_id', $cow->id)->count())->toBe(1);
});

test('milker cannot record a heat (403)', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $this->withHeaders(['Authorization' => 'Bearer '.breedingMilkerToken()])
        ->postJson('/api/v1/breeding/heats', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'heat_date' => now()->toDateString(),
        ])
        ->assertStatus(403);
});

test('service computes gestation dates', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $straw = SemenStraw::factory()->create(['bull_code' => 'BULL-001', 'quantity' => 10]);

    $serviceDate = '2026-02-01';
    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/services', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'service_date' => $serviceDate,
            'bull_id' => $straw->id,
            'inseminator' => 'AI Tech',
            'service_type' => 'AI',
        ]);

    $res->assertStatus(201);
    $data = $res->json('data');
    expect($data['expected_calving_date'])->toBe('2026-11-08T00:00:00.000000Z');
    expect($data['target_dry_date'])->toBe('2026-09-09T00:00:00.000000Z');
    expect($data['pd_window_start'])->toBe('2026-09-09T00:00:00.000000Z'); // 60 days before calving
    expect($data['pd_window_end'])->toBe('2026-10-04T00:00:00.000000Z'); // 35 days before calving
    expect($data['reproductive_status'])->toBe('open');
});

test('service on already-pregnant cow is rejected unless overridden', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    // First service
    $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/services', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'service_date' => '2026-02-01',
            'service_type' => 'AI',
        ]);

    // Mark first service as pregnant via PD check
    $pdResult = Lookup::where('type', 'pd-results')->where('name', 'Pregnant')->first();
    if (! $pdResult) {
        $pdResult = Lookup::factory()->create(['type' => 'pd-results', 'name' => 'Pregnant', 'active' => true]);
    }
    PregnancyCheck::factory()->create([
        'animal_id' => $cow->id,
        'check_date' => '2026-05-01',
        'pd_result_id' => $pdResult->id,
        'result' => 'pregnant',
    ]);

    // Second service without override → rejected
    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/services', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'service_date' => '2026-06-01',
            'service_type' => 'AI',
        ]);

    $res->assertStatus(422);
    $res->assertJson(['error' => ['code' => 'validation_failed']]);
    $fields = $res->json('error.fields');
    expect($fields['reproductive_status'][0])->toContain('already pregnant');

    // Second service with override → accepted
    $res2 = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/services', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'service_date' => '2026-06-01',
            'service_type' => 'AI',
            'override' => true,
            'override_reason' => 'Owner override for repeat service',
        ]);

    $res2->assertStatus(201);
    expect(Service::where('animal_id', $cow->id)->count())->toBe(2);
});

test('vet can view services', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    Animal::factory()->count(3)->female()->ofCategory($category->id)->create();

    $this->withHeaders(['Authorization' => 'Bearer '.breedingVetToken()])
        ->getJson('/api/v1/breeding/services')
        ->assertStatus(200);
});

test('calving creates calves, lactation, and dam updates atomically', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $dam = Animal::factory()->female()->ofCategory($category->id)->create();
    $easeScore = Lookup::where('type', 'calving-ease-scores')->where('name', 'Unassisted')->first();
    if (! $easeScore) {
        $easeScore = Lookup::factory()->create(['type' => 'calving-ease-scores', 'name' => 'Unassisted', 'active' => true]);
    }

    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/calvings', [
            'client_uuid' => (string) Str::uuid(),
            'dam_id' => $dam->id,
            'calving_date' => '2026-10-17',
            'ease_score_id' => $easeScore->id,
            'retained_placenta' => false,
            'calves' => [
                [
                    'ear_tag' => 'TEST-CALF-A',
                    'sex' => 'F',
                    'birth_weight' => 38.0,
                    'outcome' => 'live',
                    'colostrum_litres' => 3.5,
                    'colostrum_hours' => 2,
                ],
                [
                    'ear_tag' => 'TEST-CALF-B',
                    'sex' => 'M',
                    'birth_weight' => 42.0,
                    'outcome' => 'live',
                ],
            ],
        ]);

    $res->assertStatus(201);
    $data = $res->json('data');

    // Calving recorded
    expect($data['calving']['dam_ear_tag'])->toBe($dam->ear_tag);
    expect($data['calving']['retained_placenta'])->toBe(false);

    // Two calves created
    expect(count($data['calves']))->toBe(2);
    $calfEarTags = collect($data['calves'])->pluck('ear_tag')->all();
    expect(count($calfEarTags))->toBe(2);
    expect(count(array_unique($calfEarTags)))->toBe(2);

    // Lactation created
    expect($data['lactation']['lactation_number'])->toBeGreaterThanOrEqual(1);
    expect($data['lactation']['animal_id'])->toBe($dam->id);

    // Dam updated
    expect($data['dam']['id'])->toBe($dam->id);

    // Calf records exist
    expect(Animal::where('ear_tag', 'TEST-CALF-A')->count())->toBe(1);
    expect(Animal::where('ear_tag', 'TEST-CALF-B')->count())->toBe(1);
});

test('calving recorded today with a time is accepted', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $dam = Animal::factory()->female()->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/calvings', [
            'client_uuid' => (string) Str::uuid(),
            'dam_id' => $dam->id,
            'calving_date' => now()->format('Y-m-d H:i:s'),
            'calves' => [
                ['ear_tag' => 'TODAY-CALF', 'sex' => 'F', 'outcome' => 'live'],
            ],
        ]);

    $res->assertCreated();
});

test('duplicate calf ear tag is rejected', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $dam = Animal::factory()->female()->ofCategory($category->id)->create();
    Animal::factory()->create(['ear_tag' => 'CALF-DUP', 'category_id' => $category->id]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/calvings', [
            'client_uuid' => (string) Str::uuid(),
            'dam_id' => $dam->id,
            'calving_date' => '2026-10-17',
            'calves' => [
                ['ear_tag' => 'CALF-DUP', 'sex' => 'F', 'outcome' => 'live'],
            ],
        ]);

    $res->assertStatus(422);
    $res->assertJson([
        'error' => [
            'code' => 'validation_failed',
            'fields' => [
                'calves.0.ear_tag' => [
                    'The calf ear tag has already been taken.',
                ],
            ],
        ],
    ]);
});

test('owner can record pregnancy check and updates animal status', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $pdResult = Lookup::where('type', 'pd-results')->where('name', 'Pregnant')->first();
    if (! $pdResult) {
        $pdResult = Lookup::factory()->create(['type' => 'pd-results', 'name' => 'Pregnant', 'active' => true]);
    }

    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/pregnancy-checks', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'check_date' => '2026-10-17',
            'pd_result_id' => $pdResult->id,
            'result' => 'pregnant',
        ]);

    $res->assertStatus(201);
    expect($res->json('data.result'))->toBe('pregnant');

    // Animal status updated
    $cow->refresh();
    expect($cow->status->name)->toBe('Pregnant');
});

test('breeding calendar returns events', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    Service::factory()->create([
        'animal_id' => $cow->id,
        'service_date' => '2026-02-01',
        'expected_calving_date' => '2026-11-08',
        'target_dry_date' => '2026-09-03',
        'pd_window_start' => '2026-09-24',
        'pd_window_end' => '2026-10-04',
        'reproductive_status' => 'pregnant',
    ]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->getJson('/api/v1/breeding/calendar?from=2026-09-01&to=2026-12-31');

    $res->assertStatus(200);
    $events = $res->json('data');
    expect($events)->toBeArray();
    $eventTypes = collect($events)->pluck('type')->all();
    expect($eventTypes)->toContain('calving_due');
    expect($eventTypes)->toContain('dry_off_due');
    expect($eventTypes)->toContain('pd_due');
});

test('breeding KPIs return values', function () {
    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->getJson('/api/v1/breeding/kpis?from=2026-01-01&to=2026-12-31');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toHaveKeys(['conception_rate', 'services_per_conception', 'days_open', 'calving_interval']);
    expect($data['conception_rate'])->toBeNumeric();
});

test('breeding calendar and kpis require a valid date range', function () {
    $token = breedingOwnerToken();

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/breeding/calendar')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/breeding/kpis?from=2026-12-31&to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

test('semen straws CRUD', function () {
    // Create
    $res = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->postJson('/api/v1/breeding/semen-straws', [
            'bull_code' => 'BULL-TEST',
            'bull_name' => 'Test Bull',
            'breed' => 'Friesian',
            'quantity' => 10,
            'unit_price' => 45.00,
        ]);

    $res->assertStatus(201);
    expect($res->json('data.bull_code'))->toBe('BULL-TEST');

    // List
    $list = $this->withHeaders(['Authorization' => 'Bearer '.breedingOwnerToken()])
        ->getJson('/api/v1/breeding/semen-straws');
    $list->assertStatus(200);
    expect($list->json('meta.total'))->toBeGreaterThanOrEqual(1);
});

test('vet cannot create semen straws', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.breedingVetToken()])
        ->postJson('/api/v1/breeding/semen-straws', [
            'bull_code' => 'BULL-NOPE',
            'quantity' => 1,
        ])
        ->assertStatus(403);
});
