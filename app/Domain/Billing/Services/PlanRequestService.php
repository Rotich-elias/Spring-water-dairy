<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\PlanChangeRequest;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanRequestService
{
    public function __construct(private PlanService $plans) {}

    /**
     * @return Collection<int, PlanChangeRequest>
     */
    public function forTenant(int $tenantId): Collection
    {
        return $this->query()
            ->with(['plan', 'currentPlan', 'requester', 'approver'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function activeForTenant(int $tenantId): ?PlanChangeRequest
    {
        return $this->query()
            ->with(['plan', 'currentPlan'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', PlanChangeRequest::OPEN_STATUSES)
            ->orderByDesc('id')
            ->first();
    }

    public function findForTenant(int $tenantId, int $requestId): PlanChangeRequest
    {
        return $this->query()
            ->with(['plan', 'currentPlan', 'requester', 'approver'])
            ->where('tenant_id', $tenantId)
            ->whereKey($requestId)
            ->firstOrFail();
    }

    public function create(
        int $tenantId,
        ?int $requesterId,
        string $planCode,
        ?string $note,
    ): PlanChangeRequest {
        return DB::transaction(function () use ($tenantId, $requesterId, $planCode, $note): PlanChangeRequest {
            // Serialize requests for one farm so two simultaneous submissions
            // cannot both pass the single-open-request check.
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

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
                    'plan_code' => 'This plan is free and does not require approval.',
                ]);
            }

            $subscription = $this->plans->subscriptionForTenantId($tenant->id);

            if (
                (int) $subscription->plan_id === (int) $plan->id
                && in_array($subscription->status, ['active', 'trialing'], true)
            ) {
                throw ValidationException::withMessages([
                    'plan_code' => 'This plan is already active.',
                ]);
            }

            if ($this->activeForTenant($tenant->id) !== null) {
                throw ValidationException::withMessages([
                    'plan_code' => 'An upgrade request is already open for this farm.',
                ]);
            }

            $requester = $requesterId === null ? null : User::query()->find($requesterId);
            $currentPlan = Plan::query()->find($subscription->plan_id);

            $request = PlanChangeRequest::query()->create([
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
                'plan_name' => $plan->name,
                'current_plan_id' => $currentPlan?->id,
                'current_plan_code' => $currentPlan?->code,
                'current_plan_name' => $currentPlan?->name,
                'requester_id' => $requester?->id,
                'requester_name' => $requester?->name,
                'status' => PlanChangeRequest::STATUS_PENDING,
                'amount_minor' => (int) round(((float) $plan->price_monthly) * 100),
                'currency' => (string) config('plans.currency', 'KES'),
                'note' => $note,
            ]);

            $this->audit($request, $requester?->id, 'plan_request.requested', [
                'plan' => $plan->code,
                'amount_minor' => $request->amount_minor,
                'currency' => $request->currency,
            ]);

            return $request->load(['plan', 'currentPlan', 'requester']);
        });
    }

    public function cancel(int $tenantId, int $requestId): PlanChangeRequest
    {
        return DB::transaction(function () use ($tenantId, $requestId): PlanChangeRequest {
            $request = $this->query()
                ->where('tenant_id', $tenantId)
                ->whereKey($requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($request->status, PlanChangeRequest::OPEN_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'request' => 'Only an open request can be cancelled.',
                ]);
            }

            $request->forceFill([
                'status' => PlanChangeRequest::STATUS_CANCELLED,
                'canceled_at' => now(),
            ])->save();

            $this->audit($request, auth()->id(), 'plan_request.cancelled', [
                'plan' => $request->plan_code,
            ]);

            return $request->load(['plan', 'currentPlan']);
        });
    }

    /**
     * @return Collection<int, PlanChangeRequest>
     */
    public function forSeller(?string $status): Collection
    {
        return $this->query()
            ->with(['plan', 'currentPlan', 'requester', 'approver', 'tenant'])
            ->when($status, fn (Builder $query, string $value): Builder => $query->where('status', $value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findForSeller(int $requestId): PlanChangeRequest
    {
        return $this->query()
            ->with(['plan', 'currentPlan', 'requester', 'approver', 'tenant'])
            ->whereKey($requestId)
            ->firstOrFail();
    }

    public function issueInvoice(int $requestId, int $adminId): PlanChangeRequest
    {
        return DB::transaction(function () use ($requestId, $adminId): PlanChangeRequest {
            $request = $this->query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== PlanChangeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'request' => 'Only a pending request can be invoiced.',
                ]);
            }

            $request->forceFill([
                'invoice_number' => $this->invoiceNumber($request),
                'invoiced_at' => now(),
                'status' => PlanChangeRequest::STATUS_INVOICED,
            ])->save();

            $this->audit($request, $adminId, 'plan_request.invoiced', [
                'invoice_number' => $request->invoice_number,
            ]);

            return $request->load(['plan', 'currentPlan', 'tenant', 'requester']);
        });
    }

    public function markPaid(
        int $requestId,
        int $adminId,
        string $paymentReference,
        CarbonInterface $paymentDate,
    ): PlanChangeRequest {
        return DB::transaction(function () use (
            $requestId,
            $adminId,
            $paymentReference,
            $paymentDate,
        ): PlanChangeRequest {
            $request = $this->query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== PlanChangeRequest::STATUS_INVOICED) {
                throw ValidationException::withMessages([
                    'request' => 'Only an invoiced request can be marked as paid.',
                ]);
            }

            $request->forceFill([
                'payment_reference' => $paymentReference,
                'payment_date' => $paymentDate,
                'status' => PlanChangeRequest::STATUS_PAID,
            ])->save();

            $this->audit($request, $adminId, 'plan_request.payment_recorded', [
                'payment_reference' => $paymentReference,
                'payment_date' => $paymentDate->toDateString(),
            ]);

            return $request->load(['plan', 'currentPlan', 'tenant', 'requester']);
        });
    }

    public function approve(int $requestId, int $adminId): PlanChangeRequest
    {
        return DB::transaction(function () use ($requestId, $adminId): PlanChangeRequest {
            $request = $this->query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if ($request->status !== PlanChangeRequest::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'request' => 'Only a paid request can be approved.',
                ]);
            }

            $plan = Plan::active()->whereKey($request->plan_id)->first();

            if ($plan === null) {
                throw ValidationException::withMessages([
                    'request' => 'The requested plan is no longer available.',
                ]);
            }

            $tenant = Tenant::query()->whereKey($request->tenant_id)->firstOrFail();
            $paidAt = $request->payment_date ?? now();

            $subscription = $this->plans->activatePaidPlan($tenant, $plan, $paidAt);

            $request->forceFill([
                'status' => PlanChangeRequest::STATUS_APPROVED,
                'approver_id' => $adminId,
                'approver_name' => User::query()->whereKey($adminId)->value('name'),
                'approved_at' => now(),
            ])->save();

            $this->audit($request, $adminId, 'plan_request.approved', [
                'plan' => $plan->code,
                'payment_reference' => $request->payment_reference,
                'period_end' => $subscription->current_period_end?->toDateString(),
            ]);

            return $request->load(['plan', 'currentPlan', 'tenant', 'requester', 'approver']);
        });
    }

    public function reject(int $requestId, int $adminId, string $reason): PlanChangeRequest
    {
        return DB::transaction(function () use ($requestId, $adminId, $reason): PlanChangeRequest {
            $request = $this->query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if (! in_array($request->status, PlanChangeRequest::OPEN_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'request' => 'Only an open request can be rejected.',
                ]);
            }

            $request->forceFill([
                'status' => PlanChangeRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ])->save();

            $this->audit($request, $adminId, 'plan_request.rejected', [
                'plan' => $request->plan_code,
                'reason' => $reason,
            ]);

            return $request->load(['plan', 'currentPlan', 'tenant', 'requester']);
        });
    }

    private function query(): Builder
    {
        // Seller operations run outside tenant middleware; every lookup is
        // explicitly scoped by the caller or intentionally platform-wide.
        return PlanChangeRequest::withoutGlobalScopes();
    }

    private function invoiceNumber(PlanChangeRequest $request): string
    {
        return sprintf('INV-%s-%05d', now()->year, $request->id);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(PlanChangeRequest $request, ?int $userId, string $action, array $context): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $request->tenant_id,
            'auditable_type' => PlanChangeRequest::class,
            'auditable_id' => $request->id,
            'user_id' => $userId,
            'action' => $action,
            'new_values' => $context,
            'overrides' => ['tenant_id' => $request->tenant_id],
            'ip_address' => request()->ip(),
            'device' => request()->header('User-Agent'),
        ]);
    }
}
