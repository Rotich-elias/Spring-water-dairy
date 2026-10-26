<?php

use App\Models\Lookup;
use App\Models\Setting;
use App\Models\User;

test('completing setup persists farm settings and lookups', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/setup/complete', [
            'farm_name' => 'Green Valley Dairy',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'units' => 'metric',
            'pens' => ['Milking Parlour', 'Maternity'],
            'breeds' => ['Friesian', 'Jersey'],
        ]);

    $res->assertOk();
    expect($res->json('data.completed'))->toBeTrue();

    expect(Setting::where('key', 'farm_name')->value('value'))->toBe('Green Valley Dairy');
    expect(Setting::where('key', 'timezone')->value('value'))->toBe('Africa/Lagos');
    expect(Setting::where('key', 'setup_completed')->value('value'))->toBe('1');

    $penNames = Lookup::where('type', 'pens')
        ->whereNull('deleted_at')
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
    expect($penNames)->toBe(['Milking Parlour', 'Maternity']);

    $breedNames = Lookup::where('type', 'breeds')
        ->whereNull('deleted_at')
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
    expect($breedNames)->toBe(['Friesian', 'Jersey']);
});

test('setup status reports completed after setup completes', function () {
    $token = $this->authenticateAsOwner();

    $before = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/setup/status');
    expect($before->json('data.completed'))->toBeFalse();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/setup/complete', [
            'farm_name' => 'Green Valley Dairy',
        ])
        ->assertOk();

    $after = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/setup/status');
    expect($after->json('data.completed'))->toBeTrue();
});

test('setup rejects an invalid timezone', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/setup/complete', ['timezone' => 'Mars/Olympus'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

test('milker cannot complete setup', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $token = $milker->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/setup/complete', ['farm_name' => 'X'])
        ->assertStatus(403);
});
