<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\PaymentCustomer;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentWebhookService
{
    public function __construct(
        private PlanService $plans,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Process a verified provider event exactly once. The webhook response is
     * acknowledged by the controller before any provider API call is made.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $eventType = (string) (data_get($payload, 'event') ?: data_get($payload, 'type') ?: 'unknown');
        $eventId = (string) (
            data_get($payload, 'id')
            ?: (data_get($payload, 'event_id') ?: hash('sha256', (string) json_encode($payload)))
        );
        $data = data_get($payload, 'data');

        if (! is_array($data)) {
            $data = [];
        }

        DB::transaction(function () use ($payload, $eventType, $eventId, $data): void {
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'provider' => $this->gateway->name(),
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $inserted) {
                return;
            }

            try {
                match ($eventType) {
                    'charge.success' => $this->handleChargeSuccess($data),
                    'subscription.create' => $this->handleSubscriptionCreated($data),
                    'subscription.not_renew', 'subscription.disable' => $this->handleSubscriptionCancelled($data),
                    'invoice.payment_failed' => $this->handlePaymentFailed($data),
                    'invoice.payment_success', 'invoice.update' => $this->handleRenewalSuccess($data, $eventType),
                    default => null,
                };
            } finally {
                DB::table('webhook_events')
                    ->where('provider', $this->gateway->name())
                    ->where('event_id', $eventId)
                    ->update(['processed_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleChargeSuccess(array $data): void
    {
        $checkout = $this->checkoutFor($data);

        if ($checkout === null || $checkout->status === CheckoutSession::STATUS_COMPLETED) {
            // A charge without a checkout is a recurring renewal.
            $this->handleRenewalSuccess($data, 'charge.success');

            return;
        }

        $tenant = $checkout->tenant;
        $plan = $checkout->plan;

        if ($tenant === null || $plan === null) {
            return;
        }

        $providerFields = $this->providerFields($data);
        $periodEnd = $this->periodEnd($data);

        $checkout->forceFill([
            'status' => CheckoutSession::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        $this->storeCustomer($tenant, $data);

        $payment = $this->storePayment(
            $tenant,
            $data,
            $checkout,
            Payment::STATUS_PAID,
            $providerFields,
        );

        $subscription = $this->plans->activatePaidPlan($tenant, $plan, $periodEnd, $providerFields);

        $payment->forceFill(['subscription_id' => $subscription->id])->save();

        $this->audit($tenant->id, $checkout->user_id, 'payment.succeeded', [
            'checkout_id' => $checkout->id,
            'plan' => $plan->code,
            'reference' => $payment->provider_reference,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionCreated(array $data): void
    {
        $checkout = $this->checkoutFor($data) ?? $this->checkoutByCustomer($data);
        $subscription = $checkout?->tenant === null
            ? $this->subscriptionFor($data)
            : $this->plans->subscriptionForTenantId($checkout->tenant_id);
        $tenant = $checkout?->tenant ?? $subscription?->tenant;

        if ($tenant === null) {
            return;
        }

        $providerFields = $this->providerFields($data);
        $this->plans->linkProviderSubscription($tenant, $providerFields);
        $this->storeCustomer($tenant, $data);

        $this->audit($tenant->id, $checkout?->user_id, 'subscription.provider_linked', [
            'provider_subscription_id' => $providerFields['provider_subscription_id'] ?? null,
            'provider_plan_code' => $providerFields['provider_plan_code'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleRenewalSuccess(array $data, string $eventType): void
    {
        // invoice.update is emitted after every charge attempt; only treat the
        // paid state as a renewal.
        if ($eventType === 'invoice.update'
            && ! in_array(strtolower((string) (data_get($data, 'status') ?: '')), ['success', 'paid'], true)) {
            return;
        }

        $subscription = $this->subscriptionFor($data);

        if ($subscription?->tenant === null) {
            return;
        }

        $tenant = $subscription->tenant;
        $providerFields = $this->providerFields($data);
        $periodEnd = $this->periodEnd($data);

        $this->plans->linkProviderSubscription($tenant, $providerFields);
        $this->plans->recordRenewalPayment($tenant, $periodEnd);

        $reference = $this->reference(
            $data,
            'renewal-'.(string) (data_get($data, 'id') ?: data_get($data, 'invoice_code') ?: 'renewal')
        );

        $payment = $this->storePayment(
            $tenant,
            $data,
            null,
            Payment::STATUS_PAID,
            $providerFields,
            $reference,
        );
        $payment->forceFill(['subscription_id' => $subscription->id])->save();

        $this->audit($tenant->id, null, 'payment.renewed', [
            'reference' => $payment->provider_reference,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handlePaymentFailed(array $data): void
    {
        $subscription = $this->subscriptionFor($data);

        if ($subscription?->tenant === null) {
            return;
        }

        $tenant = $subscription->tenant;
        $providerFields = $this->providerFields($data);
        $periodEnd = $this->periodEnd($data);

        $this->plans->linkProviderSubscription($tenant, $providerFields);
        $this->plans->markPaymentFailed($tenant, $periodEnd);

        $payment = $this->storePayment(
            $tenant,
            $data,
            null,
            Payment::STATUS_FAILED,
            $providerFields,
            $this->reference($data, 'failed-'.(string) (data_get($data, 'invoice_code') ?: data_get($data, 'id') ?: 'invoice')),
        );
        $payment->forceFill(['subscription_id' => $subscription->id])->save();

        $this->audit($tenant->id, null, 'payment.failed', [
            'reference' => $payment->provider_reference,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionCancelled(array $data): void
    {
        $subscription = $this->subscriptionFor($data);

        if ($subscription?->tenant === null) {
            return;
        }

        $tenant = $subscription->tenant;
        $providerFields = $this->providerFields($data);
        $this->plans->linkProviderSubscription($tenant, $providerFields);

        if ($subscription->canceled_at === null) {
            $this->plans->cancelForTenant($tenant, false);
        }

        $this->audit($tenant->id, null, 'subscription.cancellation_scheduled', [
            'provider_subscription_id' => $providerFields['provider_subscription_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeCustomer(Tenant $tenant, array $data): ?PaymentCustomer
    {
        $providerFields = $this->providerFields($data);
        $customerId = $providerFields['provider_customer_id'] ?? null;
        $email = $this->customerEmail($data);

        if ($customerId === null) {
            return null;
        }

        $customer = PaymentCustomer::withoutGlobalScopes()->firstOrNew([
            'provider' => $this->gateway->name(),
            'provider_customer_id' => $customerId,
        ]);
        $customer->tenant_id = $tenant->id;
        $customer->email = $email ?? $customer->email;
        $customer->save();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $providerFields
     */
    private function storePayment(
        Tenant $tenant,
        array $data,
        ?CheckoutSession $checkout,
        string $status,
        array $providerFields,
        ?string $reference = null,
    ): Payment {
        $reference ??= $this->reference($data)
            ?? 'payment-'.hash('sha256', (string) json_encode([
                'event' => data_get($data, 'id'),
                'amount' => data_get($data, 'amount'),
                'reference' => data_get($data, 'reference'),
            ]));
        $amount = data_get($data, 'invoice.amount', data_get($data, 'amount', $checkout?->amount_minor ?? 0));
        $transactionId = $this->stringValue(data_get($data, 'id'));

        $query = Payment::withoutGlobalScopes()->where('provider', $this->gateway->name());
        $payment = (clone $query)->where('provider_reference', $reference)->first();

        if ($payment === null && $transactionId !== null) {
            $payment = (clone $query)->where('provider_transaction_id', $transactionId)->first();
        }

        $payment ??= new Payment([
            'provider' => $this->gateway->name(),
            'provider_reference' => $reference,
        ]);

        $payment->fill([
            'tenant_id' => $tenant->id,
            'checkout_session_id' => $checkout?->id,
            'subscription_id' => $payment->subscription_id,
            'provider_transaction_id' => $transactionId,
            'provider_invoice_id' => $this->stringValue(data_get($data, 'invoice_code')),
            'amount_minor' => (int) $amount,
            'currency' => strtoupper((string) (data_get($data, 'currency') ?: 'KES')),
            'status' => $status,
            'paid_at' => $status === Payment::STATUS_PAID
                ? $this->carbon(data_get($data, 'paid_at') ?: data_get($data, 'paidAt')) ?? now()
                : null,
            'receipt_url' => $this->stringValue(data_get($data, 'receipt_url')),
        ]);
        $payment->save();

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkoutFor(array $data): ?CheckoutSession
    {
        $metadata = $this->metadata($data);
        $checkoutId = $this->stringValue($metadata['checkout_id'] ?? data_get($data, 'metadata.checkout_id'));
        $reference = $this->reference($data);

        $query = CheckoutSession::withoutGlobalScopes()
            ->where('provider', $this->gateway->name());

        if ($checkoutId !== null) {
            return $query->where('id', (int) $checkoutId)->first();
        }

        if ($reference === null) {
            return null;
        }

        return $query->where(function ($query) use ($reference): void {
            $query->where('reference', $reference)
                ->orWhere('provider_session_id', $reference);
        })->latest()->first();
    }

    /**
     * Some subscription events do not repeat checkout metadata. Match the most
     * recent checkout for the same customer and provider plan instead.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkoutByCustomer(array $data): ?CheckoutSession
    {
        $email = $this->customerEmail($data);

        if ($email === null) {
            return null;
        }

        $planCode = $this->stringValue(data_get($data, 'plan.plan_code') ?: data_get($data, 'plan.code'));

        return CheckoutSession::withoutGlobalScopes()
            ->where('provider', $this->gateway->name())
            ->where('customer_email', $email)
            ->when($planCode !== null, function ($query) use ($planCode): void {
                $query->whereHas('plan', fn ($planQuery) => $planQuery
                    ->where('code', $planCode)
                    ->orWhere('provider_price_id', $planCode));
            })
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function subscriptionFor(array $data): ?Subscription
    {
        $providerFields = $this->providerFields($data);

        $query = Subscription::query();

        if (($code = $providerFields['provider_subscription_id'] ?? null) !== null) {
            $found = (clone $query)->where('provider_subscription_id', $code)->first();
            if ($found !== null) {
                return $found;
            }
        }

        if (($customerId = $providerFields['provider_customer_id'] ?? null) !== null) {
            $found = (clone $query)->where('provider_customer_id', $customerId)->first();
            if ($found !== null) {
                return $found;
            }
        }

        $email = $this->customerEmail($data);

        if ($email === null) {
            return null;
        }

        $checkout = CheckoutSession::withoutGlobalScopes()
            ->where('customer_email', $email)
            ->latest()
            ->first();

        return $checkout?->tenant_id === null
            ? null
            : (clone $query)->where('tenant_id', $checkout->tenant_id)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function providerFields(array $data): array
    {
        $customer = data_get($data, 'customer');
        $subscription = data_get($data, 'subscription');
        $plan = data_get($data, 'plan');

        return [
            'provider_customer_id' => $this->stringValue(
                is_array($customer)
                    ? ($customer['customer_code'] ?? $customer['id'] ?? null)
                    : $customer
            ),
            'provider_subscription_id' => $this->stringValue(
                is_array($subscription)
                    ? ($subscription['subscription_code'] ?? $subscription['code'] ?? $subscription['id'] ?? null)
                    : $subscription
            ) ?? $this->stringValue(data_get($data, 'subscription_code')),
            'provider_email_token' => $this->stringValue(
                is_array($subscription) ? ($subscription['email_token'] ?? null) : data_get($data, 'email_token')
            ),
            'provider_plan_code' => $this->stringValue(
                is_array($plan) ? ($plan['plan_code'] ?? $plan['code'] ?? null) : $plan
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function customerEmail(array $data): ?string
    {
        $customer = data_get($data, 'customer');

        return $this->stringValue(
            is_array($customer) ? ($customer['email'] ?? null) : data_get($data, 'email')
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metadata(array $data): array
    {
        $metadata = data_get($data, 'metadata', []);

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reference(array $data, ?string $fallback = null): ?string
    {
        return $this->stringValue(
            data_get($data, 'reference')
            ?? data_get($data, 'transaction_reference')
            ?? data_get($data, 'metadata.reference')
            ?? $fallback
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function periodEnd(array $data): ?CarbonInterface
    {
        foreach (['subscription.next_payment_date', 'next_payment_date', 'data.next_payment_date'] as $key) {
            $value = $this->carbon(data_get($data, $key));

            if ($value !== null && $value->isFuture()) {
                return $value;
            }
        }

        $paidAt = $this->carbon(data_get($data, 'paid_at') ?: data_get($data, 'paidAt'));

        return $paidAt?->copy()->addMonth();
    }

    private function carbon(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(int $tenantId, ?int $userId, string $action, array $context): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'auditable_type' => CheckoutSession::class,
            'auditable_id' => (int) ($context['checkout_id'] ?? 0),
            'user_id' => $userId,
            'action' => $action,
            'new_values' => json_encode($context),
            'ip_address' => request()->ip(),
            'device' => request()->header('User-Agent'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
