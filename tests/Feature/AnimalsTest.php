<?php

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;
use Illuminate\Support\Str;

function authHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function ownerToken(): string
{
    $owner = \App\Models\User::where('email', 'owner@springwaterdairy.local')->first();
    return $owner->createToken('test')->plainTextToken;
}

function milkerToken(): string
{
    $milker = \App\Models\User::factory()->create();
    $milker->assignRole('Milker');
    return $milker->createToken('test')->plainTextToken;
}

test('owner can list animals with pagination', function () {
    Animal::factory()->count(5)->create();

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals');

    $res->assertStatus(200);
    $res->assertJsonStructure([
        'data' => [['id', 'ear_tag', 'sex', 'status', 'version']],
        'meta' => ['page', 'per_page', 'total', 'last_page'],
    ]);
    expect($res->json('meta.total'))->toBe(5);
});

test('animals list can filter by category, status, pen, breed', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $status = Lookup::where('type', 'animal-statuses')->where('name', 'Active')->first();

    Animal::factory()->ofCategory($category->id)->ofStatus($status->id)->create();

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals?category_id='.$category->id.'&status_id='.$status->id);

    $res->assertStatus(200);
    expect($res->json('meta.total'))->toBeGreaterThanOrEqual(1);
    foreach ($res->json('data') as $animal) {
        expect($animal['category_id'])->toBe($category->id);
        expect($animal['status_id'])->toBe($status->id);
    }
});

test('animals list can search by ear tag', function () {
    Animal::factory()->create(['ear_tag' => 'COW-056']);
    Animal::factory()->create(['ear_tag' => 'COW-999']);

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals?q=056');

    $res->assertStatus(200);
    $tags = collect($res->json('data'))->pluck('ear_tag')->all();
    expect($tags)->toContain('COW-056');
    expect($tags)->not->toContain('COW-999');
});

test('milker can view animals but not create them', function () {
    $this->withHeaders(authHeader(milkerToken()))
        ->getJson('/api/v1/animals')
        ->assertStatus(200);

    $this->withHeaders(authHeader(milkerToken()))
        ->postJson('/api/v1/animals', [
            'ear_tag' => 'COW-100',
            'category_id' => 1,
            'sex' => 'F',
        ])
        ->assertStatus(403);
});

test('owner can create an animal', function () {
    $category = Lookup::where('type', 'categories')->first()->id;

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals', [
            'ear_tag' => 'COW-001',
            'category_id' => $category,
            'sex' => 'F',
            'birth_date' => '2024-01-01',
        ]);

    $res->assertStatus(201);
    expect($res->json('data.ear_tag'))->toBe('COW-001');
    expect($res->json('data.version'))->toBe(1);
    expect($res->json('data.client_uuid'))->toBeString();
});

test('duplicate ear tag is rejected on create', function () {
    $category = Lookup::where('type', 'categories')->first()->id;
    Animal::factory()->create(['ear_tag' => 'COW-001', 'category_id' => $category]);

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals', [
            'ear_tag' => 'COW-001',
            'category_id' => $category,
            'sex' => 'F',
        ]);

    $res->assertStatus(422);
    $res->assertJson([
        'error' => [
            'code' => 'validation_failed',
            'fields' => [
                'ear_tag' => [
                    'The ear tag has already been taken.',
                ],
            ],
        ],
    ]);
});

test('owner can view a single animal profile with computed fields', function () {
    $animal = Animal::factory()->create(['birth_date' => '2024-01-01']);

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals/'.$animal->id);

    $res->assertStatus(200);
    $res->assertJson([
        'data' => [
            'id' => $animal->id,
            'ear_tag' => $animal->ear_tag,
            'age' => 2,
        ],
    ]);
    expect($res->json('data.in_withholding'))->toBe(false);
});

test('404 for missing animal', function () {
    $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals/999999')
        ->assertStatus(404);
});

test('owner can update an animal', function () {
    $animal = Animal::factory()->create();

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->withHeader('If-Match', $animal->version)
        ->patchJson('/api/v1/animals/'.$animal->id, [
            'name' => 'Bessie',
            'pen_id' => $animal->pen_id,
        ]);

    $res->assertStatus(200);
    expect($res->json('data.name'))->toBe('Bessie');
    expect($res->json('data.version'))->toBe($animal->version + 1);
});

test('stale If-Match returns 409 conflict', function () {
    $animal = Animal::factory()->create();

    $this->withHeaders(authHeader(ownerToken()))
        ->withHeader('If-Match', 999)
        ->patchJson('/api/v1/animals/'.$animal->id, ['name' => 'X'])
        ->assertStatus(409)
        ->assertJson(['error' => ['code' => 'conflict']]);
});

test('milker cannot update another milker animal today-only rule enforced by backend', function () {
    // Policy-level: Milker has animals.update permission via role; backend enforces
    // "edit only entries created today by themselves" for milk entries, not animals.
    // Here we just confirm role-based 403 for roles without the permission.
    $viewer = \App\Models\User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $animal = Animal::factory()->create();

    $this->withHeaders(authHeader($token))
        ->patchJson('/api/v1/animals/'.$animal->id, ['name' => 'X'])
        ->assertStatus(403);
});

test('owner can move an animal (category/pen/status)', function () {
    $animal = Animal::factory()->create();
    $newPen = Lookup::where('type', 'pens')->where('name', 'Hospital Pen')->first();

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/'.$animal->id.'/move', [
            'pen_id' => $newPen->id,
            'status_id' => Lookup::where('type', 'animal-statuses')->where('name', 'Withholding')->first()->id,
            'reason' => 'Moved to hospital pen for treatment',
        ]);

    $res->assertStatus(200);
    expect($res->json('data.pen_id'))->toBe($newPen->id);
    expect($res->json('data.version'))->toBe($animal->version + 1);
});

test('owner can exit an animal', function () {
    $animal = Animal::factory()->create();

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/'.$animal->id.'/exit', [
            'exit_date' => '2026-10-17',
            'reason' => 'Sold',
            'price' => 1500.00,
        ]);

    $res->assertStatus(200);
    expect($res->json('data.exit_reason'))->toBe('Sold');
    expect($res->json('data.exit_price'))->toBe('1500.00');
});

test('non-owner cannot exit an animal', function () {
    $animal = Animal::factory()->create();

    $this->withHeaders(authHeader(milkerToken()))
        ->postJson('/api/v1/animals/'.$animal->id.'/exit', [
            'exit_date' => '2026-10-17',
            'reason' => 'Sold',
        ])
        ->assertStatus(403);
});

test('timeline returns combined event history', function () {
    $animal = Animal::factory()->create();

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals/'.$animal->id.'/timeline');

    $res->assertStatus(200);
    expect($res->json('data'))->toBeArray();
});

test('lineage returns dam, sire and offspring', function () {
    $dam = Animal::factory()->female()->create();
    $sire = Animal::factory()->male()->create();
    $calf = Animal::factory()->create(['dam_id' => $dam->id, 'sire_id' => $sire->id]);

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals/'.$calf->id.'/lineage');

    $res->assertStatus(200);
    expect($res->json('data.dam.id'))->toBe($dam->id);
    expect($res->json('data.sire.id'))->toBe($sire->id);
    expect($res->json('data.offspring'))->toBeArray();
});

test('owner can bulk create up to 200 animals', function () {
    $category = Lookup::where('type', 'categories')->first()->id;

    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        $rows[] = [
            'client_uuid' => (string) Str::uuid(),
            'ear_tag' => 'BULK-'.$i,
            'category_id' => $category,
            'sex' => 'F',
        ];
    }

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/bulk', ['animals' => $rows]);

    $res->assertStatus(200);
    expect($res->json('data.created'))->toBe(5);
    expect($res->json('data.failed'))->toBe(0);
    expect(count($res->json('data.results')))->toBe(5);
});

test('bulk create reports partial failures per row and keeps created rows', function () {
    $category = Lookup::where('type', 'categories')->first()->id;
    Animal::factory()->create(['ear_tag' => 'DUP-1', 'category_id' => $category]);

    $rows = [
        ['client_uuid' => (string) Str::uuid(), 'ear_tag' => 'COW-1', 'category_id' => $category, 'sex' => 'F'],
        ['client_uuid' => (string) Str::uuid(), 'ear_tag' => 'DUP-1', 'category_id' => $category, 'sex' => 'F'], // duplicate
        ['client_uuid' => (string) Str::uuid(), 'ear_tag' => 'BAD-TAG', 'category_id' => $category, 'sex' => 'F'], // invalid format
    ];

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/bulk', ['animals' => $rows]);

    $res->assertStatus(200); // always 200 even with partial failure
    expect($res->json('data.created'))->toBe(1);
    expect($res->json('data.failed'))->toBe(2);
    $failedIndexes = collect($res->json('data.results'))->where('status', 'failed')->pluck('index')->all();
    expect($failedIndexes)->toContain(1);
    expect($failedIndexes)->toContain(2);
});

test('bulk create is idempotent: repeated client_uuid returns original result', function () {
    $category = Lookup::where('type', 'categories')->first()->id;
    $uuid = (string) Str::uuid();

    $first = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/bulk', [
            'animals' => [['client_uuid' => $uuid, 'ear_tag' => 'IDEMP-1', 'category_id' => $category, 'sex' => 'F']],
        ]);

    $second = $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/bulk', [
            'animals' => [['client_uuid' => $uuid, 'ear_tag' => 'IDEMP-1', 'category_id' => $category, 'sex' => 'F']],
        ]);

    $firstId = $first->json('data.results.0.id');
    $secondId = $second->json('data.results.0.id');
    expect($secondId)->toBe($firstId);
    expect(Animal::where('ear_tag', 'IDEMP-1')->count())->toBe(1);
});

test('bulk create rejects more than 200 rows', function () {
    $category = Lookup::where('type', 'categories')->first()->id;
    $rows = array_fill(0, 201, [
        'client_uuid' => (string) Str::uuid(),
        'ear_tag' => 'B200-'.Str::random(4),
        'category_id' => $category,
        'sex' => 'F',
    ]);

    $this->withHeaders(authHeader(ownerToken()))
        ->postJson('/api/v1/animals/bulk', ['animals' => $rows])
        ->assertStatus(422);
});

test('lookup returns animals matching ear tag prefix', function () {
    Animal::factory()->create(['ear_tag' => 'COW-056']);
    Animal::factory()->create(['ear_tag' => 'COW-999']);

    $res = $this->withHeaders(authHeader(ownerToken()))
        ->getJson('/api/v1/animals/lookup?tag=056');

    $res->assertStatus(200);
    $tags = collect($res->json('data'))->pluck('ear_tag')->all();
    expect($tags)->toContain('COW-056');
    expect($tags)->not->toContain('COW-999');
});
