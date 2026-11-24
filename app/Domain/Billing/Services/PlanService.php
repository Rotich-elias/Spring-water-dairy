<?php

namespace App\Domain\Billing\Services;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Animals\Models\Animal;
use App\Domain\Billing\Exceptions\PlanLimitExceededException;
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

class PlanService
{
    /** @var list<string> */
    public const USAGE_KEYS = ['animals', 'users', 'milk_records_per_month', 'alert_rules'];

    public function __construct(private TenantContext $tenantContext) {}

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
     * Switch the active tenant to an included plan.
     *
     * This remains available for internal provisioning and free Starter
     * switches. Paid upgrades from the farm UI create an approval request.
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
     * Platform provisioning path used when a farm is created or a Platform
     * Admin changes its subscription outside the tenant middleware. Farm UI
     * callers must use subscribe(), which only allows the free Starter plan.
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
        $subscription->payment_status = 'none';
        $subscription->last_payment_at = null;
        $subscription->save();

        return $subscription;
    }

    /**
     * Activate a plan after a Platform Admin records payment and approves the
     * request. The billing period starts on the recorded payment date.
     */
    public function activatePaidPlan(
        Tenant $tenant,
        Plan $plan,
        CarbonInterface $paidAt,
    ): Subscription {
        $subscription = $this->subscriptionForTenantId($tenant->id);
        $subscription->plan_id = $plan->id;
        $subscription->status = 'active';
        $subscription->ends_at = null;
        $subscription->trial_ends_at = null;
        $subscription->canceled_at = null;
        $subscription->current_period_end = $paidAt->copy()->addMonth();
        $subscription->payment_status = 'paid';
        $subscription->last_payment_at = $paidAt;
        $subscription->save();

        return $subscription->load('plan');
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

    public function cancelForTenant(Tenant $tenant): Subscription
    {
        $subscription = $this->subscriptionForTenantId($tenant->id);

        if ($subscription->canceled_at === null && $this->subscriptionIsUsable($subscription)) {
            $subscription->canceled_at = now();
            $subscription->save();
        }

        return $subscription;
    }

    /**
     * @return array{plan: Plan, subscribed_plan: Plan, subscription: Subscription, limits: array<string, mixed>, usage: array<string, int>, can_create: array<string, bool>}
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
            $subscription->last_payment_at = null;
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
}
