<?php

use App\Models\SiteSetting;

test('public site endpoint returns platform branding', function () {
    $response = $this->getJson('/api/v1/site');

    $response->assertOk()
        ->assertJsonPath('data.platform_name', 'TetoDairy')
        ->assertJsonPath('data.powered_by_label', 'Powered by Teto Technologies')
        ->assertJsonPath('data.parent_company', 'Teto Technologies');
});

test('public site endpoint hides non-public settings', function () {
    SiteSetting::create([
        'key' => 'internal_only',
        'value' => 'hidden value',
        'type' => 'string',
        'group' => 'general',
        'is_public' => false,
    ]);

    $response = $this->getJson('/api/v1/site');

    $response->assertOk();
    expect($response->json('data'))->not->toHaveKey('internal_only');
});

test('platform admin can read and update site settings reflected publicly', function () {
    $token = $this->authenticateAsPlatformAdmin();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/site')
        ->assertOk()
        ->assertJsonPath('data.platform_name', 'TetoDairy')
        ->assertJsonPath('data.business_email', 'sales@tetodairy.local');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/site', [
            'platform_name' => 'Dairy Cloud',
            'feature_items' => [['title' => 'Herd insights']],
        ])
        ->assertOk()
        ->assertJsonPath('data.platform_name', 'Dairy Cloud')
        ->assertJsonPath('data.feature_items.0.title', 'Herd insights');

    expect(SiteSetting::where('key', 'platform_name')->first()->value)->toBe('Dairy Cloud');
    expect(SiteSetting::where('key', 'feature_items')->first()->value)->toBe([['title' => 'Herd insights']]);

    $this->flushHeaders()
        ->getJson('/api/v1/site')
        ->assertOk()
        ->assertJsonPath('data.platform_name', 'Dairy Cloud');
});

test('seller site update ignores keys outside the configured defaults', function () {
    $token = $this->authenticateAsPlatformAdmin();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/site', ['unexpected_key' => 'not_allowed'])
        ->assertOk();

    expect(SiteSetting::where('key', 'unexpected_key')->exists())->toBeFalse();
});

test('seller site update rejects values of the wrong type', function () {
    $token = $this->authenticateAsPlatformAdmin();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/site', ['platform_name' => ['not', 'a', 'string']])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['platform_name']]]);
});

test('farm owner cannot access seller site settings', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/site')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/site', ['platform_name' => 'Hijacked'])
        ->assertForbidden();

    expect(SiteSetting::where('key', 'platform_name')->first()->value)->toBe('TetoDairy');
});
