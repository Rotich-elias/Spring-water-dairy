<?php

namespace App\Domain\Billing\Services;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Animals\Models\Animal;
use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Payment;
use App\Domain\Milk\Models\MilkRecord;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PlanService
{
    /** @var list<string> */
    public const USAGE_KEYS = ['animals', 'users', 'milk_records_per_month', 'alert_rules'];

    public function __construct(
        private TenantContext $tenantContext,
        private PaymentGateway $payments,
    ) {}

    /**
     * Get (or lazily create) the trialing subscription for the active tenant.
     */
    public function subscription(): Subscription
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            throw new RuntimeException('A tenant must be resolved before accessing billing.');
        }

        return $this->subscriptionForTenantId($tenantId);
    }

    /**
     * The plan that governs limits. Expired/cancelled subscriptions fall back
     * to the Starter plan while the real subscription is still returned in
     * billing responses.
     */
    public function plan(): Plan
    {
        $subscription = $this->subscription();
        $plan = $subscription->plan;

        if ($plan !== null && $plan->is_active && $this->subscriptionIsUsable($subscription)) {
            return $plan;
        }

        return $this->starterPlan();
    }

    /**
     * A null limit means unlimited usage for that key.
     */
    public function limit(string $key): ?int
    {
        $limits = $this->plan()->limits;

        if (! is_array($limits) || ! array_key_exists($key, $limits) || $limits[$key] === null) {
            return null;
        }

        return (int) $limits[$key];
    }

    public function usage(string $key): int
    {
        return match ($key) {
            'animals' => Animal::count(),
            'users' => $this->tenantContext->id() === null
                ? 0
                : DB::table('tenant_user')->where('tenant_id', $this->tenantContext->id())->count(),
            'milk_records_per_month' => MilkRecord::where('record_date', '>=', now()->startOfMonth())->count(),
            'alert_rules' => AlertRule::count(),
            default => 0,
        };
    }

    public function can(string $key, int $additional = 1): bool
    {
        $limit = $this->limit($key);

        return $limit === null || $this->usage($key) + $additional <= $limit;
    }

    /**
     * @throws PlanLimitExceededException
     */
    public function assertCan(string $key, int $additional = 1): void
    {
        $limit = $this->limit($key);

        if ($limit === null || $this->usage($key) + $additional <= $limit) {
            return;
        }

        $labels = [
            'animals' => 'animals',
            'users' => 'users',
            'milk_records_per_month' => 'milk records per month',
            'alert_rules' => 'alert rules',
        ];

        throw new PlanLimitExceededException(
            $key,
            $limit,
            'Your plan includes '.$limit.' '.($labels[$key] ?? $key).'. Upgrade your plan to add more.'
        );
    }

    /**
     * Switch the active tenant to a paid/included plan.
     *
     * This remains available for internal provisioning and free-plan changes.
     * Paid upgrades from the farm UI go through hosted checkout instead.
     *
     * @throws ValidationException
     */
    public function subscribe(string $planCode): Subscription
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            throw new RuntimeException('A tenant must be resolved before changing a subscription.');
        }

        return $this->subscribeForTenant($tenant, $planCode);
    }

    /**
     * Used when a tenant is created outside the tenant middleware and its
     * trialing subscription should start on a chosen plan.
     *
     * @throws ValidationException
     */
    public function subscribeForTenant(Tenant $tenant, string $planCode): Subscription
    {
        $plan = Plan::active()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($planCode)])
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'The selected plan is invalid.',
            ]);
        }

        $subscription = Subscription::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenant->id]);
        $subscription->plan_id = $plan->id;
        $subscription->status = 'active';
        $subscription->ends_at = null;
        $subscription->trial_ends_at = null;
        $subscription->canceled_at = null;
        $subscription->current_period_end = now()->addMonth();
        $subscription->provider = null;
        $subscription->provider_customer_id = null;
        $subscription->provider_subscription_id = null;
        $subscription->provider_email_token = null;
        $subscription->provider_plan_code = null;
        $subscription->payment_status = 'none';
        $subscription->last_payment_at = null;
        $subscription->save();

        return $subscription;
    }

    /**
     * Activate a plan after the payment provider confirms a successful charge.
     *
     * @param  array<string, string|null>  $provider
     */
    public function activatePaidPlan(
        Tenant $tenant,
        Plan $plan,
        ?CarbonInterface $periodEnd = null,
        array $provider = [],
    ): Subscription {
        $subscription = $this->subscriptionForTenantId($tenant->id);
        $subscription->plan_id = $plan->id;
        $subscription->status = 'active';
        $subscription->ends_at = null;
        $subscription->trial_ends_at = null;
        $subscription->canceled_at = null;
        $subscription->current_period_end = $periodEnd ?? now()->addMonth();
        $subscription->payment_status = 'paid';
        $subscription->last_payment_at = now();
        $subscription->provider = $this->payments->name();

        foreach ($provider as $key => $value) {
            if (in_array($key, [
                'provider_customer_id',
                'provider_subscription_id',
                'provider_email_token',
                'provider_plan_code',
            ], true) && $value !== null && $value !== '') {
                $subscription->{$key} = $value;
            }
        }

        $subscription->save();

        return $subscription->load('plan');
    }

    /**
     * Link provider identifiers from a subscription.create webhook without
     * changing the locally active plan.
     *
     * @param  array<string, string|null>  $provider
     */
    public function linkProviderSubscription(Tenant $tenant, array $provider): Subscription
    {
        $subscription = $this->subscriptionForTenantId($tenant->id);
        $subscription->provider = $this->payments->name();

        foreach ($provider as $key => $value) {
            if (in_array($key, [
                'provider_customer_id',
                'provider_subscription_id',
                'provider_email_token',
                'provider_plan_code',
            ], true) && $value !== null && $value !== '') {
                $subscription->{$key} = $value;
            }
        }

        $subscription->save();

        return $subscription;
    }

    /**
     * A failed renewal keeps access until the period ends, then the normal
     * expiry rules move the farm to Starter.
     */
    public function markPaymentFailed(Tenant $tenant, ?CarbonInterface $periodEnd = null): Subscription
    {
        $subscription = $this->subscriptionForTenantId($tenant->id);
        $subscription->payment_status = 'past_due';

        if ($periodEnd !== null && $periodEnd->isFuture()) {
            $subscription->current_period_end = $periodEnd;
        }

        $subscription->save();

        return $subscription;
    }

    public function recordRenewalPayment(Tenant $tenant, ?CarbonInterface $periodEnd = null): Subscription
    {
        $subscription = $this->subscriptionForTenantId($tenant->id);
        $subscription->payment_status = 'paid';
        $subscription->last_payment_at = now();

        if ($periodEnd !== null && $periodEnd->isFuture()) {
            $subscription->current_period_end = $periodEnd;
        }

        $subscription->save();

        return $subscription;
    }

    /**
     * Schedule cancellation at the end of the current period. The farm keeps
     * its plan limits until that date, then falls back to the starter plan.
     */
    public function cancel(): Subscription
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            throw new RuntimeException('A tenant must be resolved before cancelling a subscription.');
        }

        return $this->cancelForTenant($tenant);
    }

    public function cancelForTenant(Tenant $tenant, bool $notifyProvider = true): Subscription
    {
        $subscription = $this->subscriptionForTenantId($tenant->id);

        if ($subscription->canceled_at === null && $this->subscriptionIsUsable($subscription)) {
            $subscription->canceled_at = now();
            $subscription->save();

            if ($notifyProvider) {
                $this->disableProviderSubscription($subscription);
            }
        }

        return $subscription;
    }

    /**
     * @return array{plan: Plan, subscribed_plan: Plan, subscription: Subscription, limits: array<string, mixed>, usage: array<string, int>, can_create: array<string, bool>, pending_checkout: CheckoutSession|null, last_payment: Payment|null}
     */
    public function summary(): array
    {
        $subscription = $this->subscription();
        $plan = $this->plan();
        $limits = is_array($plan->limits) ? $plan->limits : [];

        return [
            'plan' => $plan,
            'subscribed_plan' => $subscription->plan ?? $plan,
            'subscription' => $subscription,
            'limits' => $limits,
            'usage' => collect(self::USAGE_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => $this->usage($key)])
                ->all(),
            'can_create' => collect(self::USAGE_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => $this->can($key)])
                ->all(),
            'pending_checkout' => $this->pendingCheckout($subscription->tenant_id),
            'last_payment' => $this->lastPayment($subscription->tenant_id),
        ];
    }

    public function subscriptionForTenantId(int $tenantId): Subscription
    {
        $subscription = Subscription::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId]);

        if (! $subscription->exists) {
            $subscription->plan_id = $this->starterPlan()->id;
            $subscription->status = 'trialing';
            $subscription->trial_ends_at = now()->addDays(14);
            $subscription->ends_at = null;
            $subscription->canceled_at = null;
            $subscription->current_period_end = $subscription->trial_ends_at;
            $subscription->payment_status = 'none';
            $subscription->save();

            return $subscription;
        }

        return $this->finalizeExpired($subscription);
    }

    /**
     * Close a period that has already elapsed so status, billing responses and
     * plan limits all agree without a scheduler.
     */
    private function finalizeExpired(Subscription $subscription): Subscription
    {
        if (! in_array($subscription->status, ['active', 'trialing'], true)) {
            return $subscription;
        }

        $periodEnd = $subscription->current_period_end ?? $subscription->trial_ends_at;

        if ($periodEnd === null || Carbon::parse($periodEnd)->isFuture()) {
            return $subscription;
        }

        $subscription->status = $subscription->canceled_at !== null ? 'cancelled' : 'expired';
        $subscription->ends_at = Carbon::parse($periodEnd);
        $subscription->save();

        return $subscription;
    }

    private function starterPlan(): Plan
    {
        return Plan::active()
            ->whereRaw('LOWER(code) = ?', ['starter'])
            ->first()
            ?? Plan::active()->ordered()->firstOrFail();
    }

    private function subscriptionIsUsable(Subscription $subscription): bool
    {
        if (! in_array($subscription->status, ['active', 'trialing'], true)) {
            return false;
        }

        if ($subscription->ends_at !== null && Carbon::parse($subscription->ends_at)->isPast()) {
            return false;
        }

        if ($subscription->trial_ends_at !== null && Carbon::parse($subscription->trial_ends_at)->isPast()) {
            return false;
        }

        if ($subscription->current_period_end !== null && Carbon::parse($subscription->current_period_end)->isPast()) {
            return false;
        }

        return true;
    }

    private function disableProviderSubscription(Subscription $subscription): void
    {
        $code = $subscription->provider_subscription_id;

        if ($code === null || $code === '') {
            return;
        }

        try {
            $this->payments->disableSubscription($code, $subscription->provider_email_token);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function pendingCheckout(int $tenantId): ?CheckoutSession
    {
        CheckoutSession::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CheckoutSession::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => CheckoutSession::STATUS_EXPIRED]);

        return CheckoutSession::withoutGlobalScopes()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('status', CheckoutSession::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->latest()
            ->first();
    }

    private function lastPayment(int $tenantId): ?Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();
    }
}
