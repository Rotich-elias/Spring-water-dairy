<?php

use App\Models\User;

test('owner can update appearance and public settings expose the theme', function () {
    $token = $this->authenticateAsOwner();

    $payload = [
        'theme_mode' => 'dark',
        'theme_preset' => 'ocean',
        'theme_primary' => '#112233',
        'theme_accent' => '#445566',
        'farm_name' => 'Ocean Dairy',
    ];

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/appearance', $payload)
        ->assertStatus(200)
        ->assertJson(['data' => $payload]);

    $public = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/settings/public')
        ->assertStatus(200)
        ->json('data');

    expect($public['theme_mode'])->toBe('dark');
    expect($public['theme_preset'])->toBe('ocean');
    expect($public['theme_primary'])->toBe('#112233');
    expect($public['theme_accent'])->toBe('#445566');
    expect($public['farm_name'])->toBe('Ocean Dairy');
});

test('a viewer cannot update appearance', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/appearance', [
            'theme_mode' => 'dark',
            'theme_preset' => 'ocean',
            'theme_primary' => '#112233',
            'theme_accent' => '#445566',
            'farm_name' => 'Ocean Dairy',
        ])
        ->assertStatus(403);
});
