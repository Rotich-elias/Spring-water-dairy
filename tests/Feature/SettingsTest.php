<?php

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
