<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\PaymentCustomer;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CheckoutService
{
    public function __construct(
        private TenantContext $context,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Start a hosted checkout for a paid plan. The local subscription is not
     * changed here: only a verified provider webhook can activate the plan.
     */
    public function start(string $planCode): CheckoutSession
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            throw new RuntimeException('A tenant must be resolved before starting checkout.');
        }

        $plan = Plan::active()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($planCode)])
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'The selected plan is invalid.',
            ]);
        }

        if ((float) $plan->price_monthly <= 0) {
            throw ValidationException::withMessages([
                'plan_code' => 'This plan is free and does not require checkout.',
            ]);
        }

        $user = auth()->user();
        $amountMinor = (int) round(((float) $plan->price_monthly) * 100);
        $reference = 'SWD-'.$tenant->id.'-'.Str::upper(Str::random(18));

        // Replace any abandoned checkout for this farm so only one pending
        // payment can be resumed or confirmed by a webhook.
        CheckoutSession::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', CheckoutSession::STATUS_PENDING)
            ->update(['status' => CheckoutSession::STATUS_EXPIRED]);

        $checkout = CheckoutSession::forceCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'plan_id' => $plan->id,
            'provider' => $this->gateway->name(),
            'reference' => $reference,
            'customer_email' => $user?->email,
            'status' => CheckoutSession::STATUS_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => 'KES',
            'expires_at' => now()->addMinutes(30),
        ]);

        $callbackUrl = (string) config('services.paystack.callback_url');

        if ($callbackUrl === '') {
            $query = http_build_query([
                'checkout' => 'success',
                'plan' => $plan->code,
                'reference' => $checkout->reference,
            ]);
            $callbackUrl = rtrim((string) config('app.frontend_url'), '/').'/admin/billing?'.$query;
        }

        $customer = PaymentCustomer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', $this->gateway->name())
            ->first();

        $payload = [
            'email' => $user?->email ?: (string) config('plans.default_tenant.owner_email'),
            'amount' => $amountMinor,
            'currency' => 'KES',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'plan' => $plan->provider_price_id ?: $plan->code,
            'metadata' => [
                'checkout_id' => $checkout->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
            ],
        ];

        if ($customer !== null) {
            $payload['customer'] = $customer->provider_customer_id;
        }

        try {
            $providerCheckout = $this->gateway->initializeCheckout($payload);
        } catch (Throwable $e) {
            report($e);

            $checkout->forceFill([
                'status' => CheckoutSession::STATUS_FAILED,
                'failed_at' => now(),
            ])->save();

            throw ValidationException::withMessages([
                'checkout' => 'Checkout could not be started. Please try again.',
            ]);
        }

        $checkout->forceFill([
            'provider_session_id' => $providerCheckout['reference'],
            'authorization_url' => $providerCheckout['authorization_url'],
        ])->save();

        $this->audit($tenant->id, $user?->id, 'checkout.started', [
            'plan' => $plan->code,
            'checkout_id' => $checkout->id,
            'amount' => $amountMinor,
            'currency' => 'KES',
        ]);

        return $checkout->load('plan');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(int $tenantId, ?int $userId, string $action, array $context): void
    {
        DB::table('audit_logs')->insert([
            'tenant_id' => $tenantId,
            'auditable_type' => CheckoutSession::class,
            'auditable_id' => $context['checkout_id'] ?? null,
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
