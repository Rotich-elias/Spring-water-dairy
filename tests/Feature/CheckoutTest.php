<?php

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'test-secret-key');
    config()->set('services.paystack.webhook_secret', 'whsec_test_secret');

    $this->owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $this->token = $this->owner->createToken('test')->plainTextToken;

    $this->startCheckout = function (string $planCode = 'growth'): TestResponse {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/billing/checkout', ['plan_code' => $planCode]);
    };

    $this->postWebhook = function (array $event, ?string $secret = null): TestResponse {
        $raw = json_encode($event);
        $signature = hash_hmac('sha512', $raw, $secret ?? 'test-secret-key');

        return $this->call(
            'POST',
            '/api/v1/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $raw
        );
    };

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.test/session',
                'access_code' => 'ACS_TEST',
            ],
        ]),
        'api.paystack.co/subscription/disable' => Http::response(['status' => true]),
    ]);
});

function chargeEvent(CheckoutSession $checkout, string $eventId = 'evt_charge_1', ?string $nextPaymentDate = null): array
{
    return [
        'event' => 'charge.success',
        'id' => $eventId,
        'data' => [
            'id' => 5001,
            'reference' => $checkout->reference,
            'amount' => $checkout->amount_minor,
            'currency' => 'KES',
            'status' => 'success',
            'paid_at' => now()->toIso8601String(),
            'customer' => [
                'email' => 'owner@springwaterdairy.local',
                'customer_code' => 'CUS_TEST',
                'id' => 9001,
            ],
            'subscription' => [
                'subscription_code' => 'SUB_TEST',
                'email_token' => 'TOKEN_TEST',
                'next_payment_date' => ($nextPaymentDate ?? now()->addMonth()->toIso8601String()),
            ],
            'plan' => ['plan_code' => 'growth', 'code' => 'growth'],
            'metadata' => ['checkout_id' => $checkout->id],
        ],
    ];
}

test('checkout requires authentication and billing permission', function () {
    $this->postJson('/api/v1/billing/checkout', ['plan_code' => 'growth'])
        ->assertStatus(401);

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/checkout', ['plan_code' => 'growth'])
        ->assertStatus(403);
});

test('checkout creates a pending session without changing the effective plan', function () {
    $response = ($this->startCheckout)()
        ->assertCreated()
        ->assertJsonPath('data.provider', 'paystack')
        ->assertJsonPath('data.plan_code', 'growth')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.test/session');

    $checkout = CheckoutSession::firstOrFail();

    expect($checkout->amount_minor)->toBe(790000)
        ->and($checkout->currency)->toBe('KES')
        ->and($checkout->status)->toBe('pending');

    $summary = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('starter')
        ->and($summary['subscribed_plan']['code'])->toBe('starter')
        ->and($summary['pending_checkout']['id'])->toBe($checkout->id);

    Http::assertSent(function (HttpRequest $request): bool {
        $data = $request->data();

        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $data['plan'] === 'growth'
            && $data['amount'] === 790000
            && $data['currency'] === 'KES'
            && $data['metadata']['checkout_id'] === CheckoutSession::firstOrFail()->id;
    });

    expect($response->json('data.reference'))->toBe($checkout->reference);
});

test('checkout rejects free and inactive plans', function () {
    ($this->startCheckout)('starter')
        ->assertStatus(422)
        ->assertJsonPath('error.fields.plan_code.0', 'This plan is free and does not require checkout.');

    $plan = Plan::factory()->create(['code' => 'retired-checkout-plan', 'is_active' => false]);

    ($this->startCheckout)($plan->code)
        ->assertStatus(422)
        ->assertJsonPath('error.fields.plan_code.0', 'The selected plan is invalid.');
});

test('invalid webhook signatures are rejected without changing billing', function () {
    ($this->startCheckout)()->assertCreated();
    $checkout = CheckoutSession::firstOrFail();

    $raw = json_encode(chargeEvent($checkout));
    $this->call(
        'POST',
        '/api/v1/webhooks/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => str_repeat('0', 128),
        ],
        $raw
    )->assertStatus(403);

    expect(CheckoutSession::firstOrFail()->status)->toBe('pending')
        ->and(Subscription::firstOrFail()->plan->code)->toBe('starter')
        ->and(WebhookEvent::count())->toBe(0);
});

test('a signed charge webhook activates the plan once and is idempotent', function () {
    ($this->startCheckout)()->assertCreated();
    $checkout = CheckoutSession::firstOrFail();

    ($this->postWebhook)(chargeEvent($checkout))->assertOk()->assertJsonPath('received', true);

    $summary = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('growth')
        ->and($summary['subscription']['status'])->toBe('active')
        ->and($summary['subscription']['payment_status'])->toBe('paid')
        ->and($summary['subscription']['provider_subscription_id'])->toBe('SUB_TEST')
        ->and($summary['subscription']['provider_customer_id'])->toBe('CUS_TEST')
        ->and($summary['pending_checkout'])->toBeNull()
        ->and($summary['last_payment']['status'])->toBe('paid');

    expect(CheckoutSession::firstOrFail()->status)->toBe('completed')
        ->and(Payment::count())->toBe(1)
        ->and(WebhookEvent::firstOrFail()->processed_at)->not->toBeNull();

    ($this->postWebhook)(chargeEvent($checkout))->assertOk();

    expect(Payment::count())->toBe(1)
        ->and(WebhookEvent::count())->toBe(1);
});

test('a failed renewal keeps the plan until the current period ends', function () {
    ($this->startCheckout)()->assertCreated();
    $checkout = CheckoutSession::firstOrFail();

    ($this->postWebhook)(chargeEvent($checkout))->assertOk();

    $before = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');
    $periodEnd = $before['subscription']['current_period_end'];

    ($this->postWebhook)([
        'event' => 'invoice.payment_failed',
        'id' => 'evt_failed_1',
        'data' => [
            'id' => 7001,
            'invoice_code' => 'INV_TEST',
            'amount' => 790000,
            'currency' => 'KES',
            'status' => 'failed',
            'subscription' => ['subscription_code' => 'SUB_TEST'],
            'customer' => ['email' => 'owner@springwaterdairy.local', 'customer_code' => 'CUS_TEST'],
        ],
    ])->assertOk();

    $after = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($after['plan']['code'])->toBe('growth')
        ->and($after['subscription']['status'])->toBe('active')
        ->and($after['subscription']['payment_status'])->toBe('past_due')
        ->and($after['subscription']['current_period_end'])->toBe($periodEnd);

    $this->travelTo(now()->parse($periodEnd)->addDay());

    try {
        $expired = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/billing')
            ->assertOk()
            ->json('data');

        expect($expired['plan']['code'])->toBe('starter')
            ->and($expired['subscription']['status'])->toBe('expired');
    } finally {
        $this->travelBack();
    }
});

test('cancelling a paid subscription disables the provider subscription but keeps access until period end', function () {
    ($this->startCheckout)()->assertCreated();
    ($this->postWebhook)(chargeEvent(CheckoutSession::firstOrFail()))->assertOk();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/cancel')
        ->assertOk()
        ->assertJsonPath('data.plan.code', 'growth');

    expect($response->json('data.subscription.canceled_at'))->not->toBeNull();

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.paystack.co/subscription/disable'
            && $request->data()['code'] === 'SUB_TEST'
            && $request->data()['token'] === 'TOKEN_TEST';
    });
});

test('a signing secret webhook is accepted', function () {
    ($this->startCheckout)()->assertCreated();
    $checkout = CheckoutSession::firstOrFail();
    $raw = json_encode(chargeEvent($checkout, 'evt_signing_secret'));
    $timestamp = time();
    $signature = hash_hmac('sha512', $timestamp.'.'.$raw, 'whsec_test_secret');

    $this->call(
        'POST',
        '/api/v1/webhooks/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYSTACK_SIGNATURE' => 't='.$timestamp.',v1=whk_test:'.$signature,
        ],
        $raw
    )->assertOk()->assertJsonPath('received', true);

    expect(Subscription::firstOrFail()->plan->code)->toBe('growth');
});

test('subscription.not_renew schedules cancellation without immediately removing plan access', function () {
    ($this->startCheckout)()->assertCreated();
    ($this->postWebhook)(chargeEvent(CheckoutSession::firstOrFail()))->assertOk();

    ($this->postWebhook)([
        'event' => 'subscription.not_renew',
        'id' => 'evt_not_renew',
        'data' => [
            'subscription_code' => 'SUB_TEST',
            'email_token' => 'TOKEN_TEST',
            'customer' => ['email' => 'owner@springwaterdairy.local', 'customer_code' => 'CUS_TEST'],
        ],
    ])->assertOk();

    $summary = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('growth')
        ->and($summary['subscription']['canceled_at'])->not->toBeNull();
});

test('checkout sends the configured Paystack plan code when one is set', function () {
    $plan = Plan::where('code', 'growth')->firstOrFail();
    $plan->forceFill(['provider_price_id' => 'PLN_GROWTH_KES'])->save();

    ($this->startCheckout)()->assertCreated();

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->data()['plan'] === 'PLN_GROWTH_KES';
    });
});

test('a repeated charge for the same transaction does not create a second payment', function () {
    ($this->startCheckout)()->assertCreated();
    $checkout = CheckoutSession::firstOrFail();

    ($this->postWebhook)(chargeEvent($checkout, 'evt_charge_first'))->assertOk();
    ($this->postWebhook)(chargeEvent($checkout, 'evt_charge_repeat'))->assertOk();

    expect(Payment::count())->toBe(1)
        ->and(WebhookEvent::count())->toBe(2)
        ->and(Subscription::firstOrFail()->plan->code)->toBe('growth');
});
