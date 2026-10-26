<?php

use App\Domain\Milk\MilkSessionSchedule;
use App\Models\Setting;
use App\Models\User;

test('owner can list all settings', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/settings');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveKey('farm_name');
    expect($data)->toHaveKey('timezone');
    expect($data['farm_name'])->toBe('Spring Water Dairy');
});

test('owner can update settings by key', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/settings', [
            'settings' => [
                'farm_name' => 'Updated Dairy',
                'timezone' => 'Africa/Nairobi',
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('data.farm_name'))->toBe('Updated Dairy');

    // Value is persisted.
    $setting = Setting::where('key', 'farm_name')->first();
    expect($setting->value)->toBe('Updated Dairy');
});

test('non-owner cannot update settings', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $token = $milker->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/settings', ['settings' => ['farm_name' => 'X']])
        ->assertStatus(403);
});

test('operational roles can read public settings', function () {
    Setting::create([
        'key' => 'private_test_setting',
        'value' => 'hidden',
        'type' => 'string',
        'is_public' => false,
    ]);

    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $token = $milker->createToken('test')->plainTextToken;

    try {
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/settings/public');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveKey('milk_sessions_per_day');
        expect($response->json('data'))->not->toHaveKey('private_test_setting');
    } finally {
        Setting::where('key', 'private_test_setting')->forceDelete();
    }
});

test('milk sessions per day setting accepts one to three', function () {
    $token = $this->authenticateAsOwner();
    $original = Setting::where('key', 'milk_sessions_per_day')->value('value');

    try {
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/settings', ['settings' => ['milk_sessions_per_day' => 3]])
            ->assertStatus(200);

        expect(Setting::where('key', 'milk_sessions_per_day')->first()->value)->toBe(3);
        expect(MilkSessionSchedule::enabled())->toBe(['morning', 'midday', 'evening']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/settings', ['settings' => ['milk_sessions_per_day' => 4]])
            ->assertStatus(422);
    } finally {
        Setting::where('key', 'milk_sessions_per_day')->update(['value' => $original]);
    }
});
