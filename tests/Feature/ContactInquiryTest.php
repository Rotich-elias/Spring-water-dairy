<?php

use App\Models\ContactInquiry;

function inquiryPayload(): array
{
    return [
        'name' => 'Jane Wanjiku',
        'farm_name' => 'Green Acres Dairy',
        'email' => 'jane@example.com',
        'phone' => '+254712345678',
        'message' => 'I would like a demonstration of the platform.',
    ];
}

test('public contact endpoint validates required fields', function () {
    $this->postJson('/api/v1/contact-inquiries', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['message', 'fields' => ['name', 'email', 'message']]]);
});

test('public contact endpoint rejects a message that is too short', function () {
    $payload = inquiryPayload();
    $payload['message'] = 'Hi';

    $this->postJson('/api/v1/contact-inquiries', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['message']]]);
});

test('public contact endpoint stores an inquiry with new status', function () {
    $response = $this->postJson('/api/v1/contact-inquiries', inquiryPayload());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Jane Wanjiku')
        ->assertJsonPath('data.farm_name', 'Green Acres Dairy')
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.status', 'new');

    expect($response->json('data'))->toHaveKeys([
        'id', 'name', 'farm_name', 'email', 'phone', 'message', 'status', 'created_at',
    ]);

    $this->assertDatabaseHas('contact_inquiries', [
        'id' => $response->json('data.id'),
        'email' => 'jane@example.com',
        'status' => 'new',
    ]);
});

test('platform admin can list filter show update and delete inquiries', function () {
    $inquiry = ContactInquiry::create([
        ...inquiryPayload(),
        'status' => 'new',
    ]);
    $closedInquiry = ContactInquiry::create([
        'name' => 'Peter Kamau',
        'email' => 'peter@example.com',
        'message' => 'Please send me the pricing details for a larger herd.',
        'status' => 'closed',
    ]);
    $token = $this->authenticateAsPlatformAdmin();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/inquiries')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($inquiry->id, $closedInquiry->id);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/inquiries?status=closed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $closedInquiry->id);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/inquiries/'.$inquiry->id)
        ->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/inquiries/'.$inquiry->id, ['status' => 'bogus'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/inquiries/'.$inquiry->id, ['status' => 'contacted'])
        ->assertOk()
        ->assertJsonPath('data.status', 'contacted');

    expect($inquiry->fresh()->status)->toBe('contacted');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v1/seller/inquiries/'.$inquiry->id)
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    $this->assertDatabaseMissing('contact_inquiries', ['id' => $inquiry->id]);
});

test('seller inquiry endpoints require authentication', function () {
    $this->getJson('/api/v1/seller/inquiries')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('farm owner cannot view or manage seller inquiries', function () {
    $inquiry = ContactInquiry::create(inquiryPayload());
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/inquiries')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/inquiries/'.$inquiry->id)
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/seller/inquiries/'.$inquiry->id, ['status' => 'closed'])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v1/seller/inquiries/'.$inquiry->id)
        ->assertForbidden();

    expect($inquiry->fresh()->status)->toBe('new');
});
