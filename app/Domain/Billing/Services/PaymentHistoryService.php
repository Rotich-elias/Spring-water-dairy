<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\PlanChangeRequest;
use App\Domain\Tenancy\Models\Subscription;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the payment ledger and statistics shown to farm owners and to the
 * platform admin. The manual workflow records every payment as a
 * PlanChangeRequest, so that table is the single source of truth here.
 */
class PaymentHistoryService
{
    /** Number of months included in the revenue trend. */
    private const TREND_MONTHS = 6;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, statistics: array<string, mixed>}
     */
    public function forTenant(int $tenantId, array $filters): array
    {
        return $this->build($filters, $tenantId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, statistics: array<string, mixed>}
     */
    public function forSeller(array $filters): array
    {
        return $this->build($filters, null);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, statistics: array<string, mixed>}
     */
    private function build(array $filters, ?int $tenantId): array
    {
        $history = $this->query();

        if ($tenantId !== null) {
            $history->where('tenant_id', $tenantId);
        }

        $this->applyFilters($history, $filters);

        $paginator = $history
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters));

        return [
            'paginator' => $paginator,
            'statistics' => $this->statistics($filters, $tenantId),
        ];
    }

    private function query(): Builder
    {
        return PlanChangeRequest::withoutGlobalScopes()
            ->with(['plan', 'currentPlan', 'requester', 'approver', 'tenant']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['plan_code'])) {
            $query->where('plan_code', (string) $filters['plan_code']);
        }

        // Safe for farm requests too: the base query already restricts the
        // tenant, so supplying a tenant filter can only narrow that scope.
        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereRaw(
                'COALESCE(payment_date, created_at) >= ?',
                [Carbon::parse((string) $filters['from'])->startOfDay()->toDateTimeString()],
            );
        }

        if (! empty($filters['to'])) {
            $query->whereRaw(
                'COALESCE(payment_date, created_at) <= ?',
                [Carbon::parse((string) $filters['to'])->endOfDay()->toDateTimeString()],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? 15);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function statistics(array $filters, ?int $tenantId): array
    {
        $query = PlanChangeRequest::withoutGlobalScopes()
            ->select([
                'id',
                'tenant_id',
                'tenant_name',
                'plan_code',
                'plan_name',
                'current_plan_code',
                'status',
                'amount_minor',
                'payment_date',
                'created_at',
            ]);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $this->applyFilters($query, $filters);

        /** @var Collection<int, PlanChangeRequest> $requests */
        $requests = $query->get();
        $statusCounts = $requests->countBy('status');

        // Only recorded, non-rejected payments represent money collected.
        $collected = $requests->filter(
            fn (PlanChangeRequest $request): bool => in_array($request->status, [
                PlanChangeRequest::STATUS_PAID,
                PlanChangeRequest::STATUS_APPROVED,
            ], true),
        );

        $totalMinor = (int) $collected->sum('amount_minor');
        $paymentCount = $collected->count();

        return [
            'currency' => (string) config('plans.currency', 'KES'),
            'total_collected_minor' => $totalMinor,
            'total_collected' => round($totalMinor / 100, 2),
            'payment_count' => $paymentCount,
            'average_payment' => $paymentCount > 0 ? round($totalMinor / $paymentCount / 100, 2) : 0,
            'active_paid_farms' => $this->activePaidFarms($tenantId),
            'farms_with_payments' => $collected->pluck('tenant_id')->unique()->count(),
            'upgrades' => $collected->filter(
                fn (PlanChangeRequest $request): bool => $request->status === PlanChangeRequest::STATUS_APPROVED
                    && $request->current_plan_code !== $request->plan_code,
            )->count(),
            'cancellations' => (int) ($statusCounts[PlanChangeRequest::STATUS_CANCELLED] ?? 0),
            'monthly' => $this->monthlyTrend($collected),
            'status_breakdown' => $this->statusBreakdown($statusCounts),
            'by_plan' => $this->byPlan($collected),
            'by_farm' => $this->byFarm($collected),
        ];
    }

    /**
     * Platform-wide active paid subscriptions; never exposed to farm users.
     */
    private function activePaidFarms(?int $tenantId): ?int
    {
        if ($tenantId !== null) {
            return null;
        }

        return Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'trialing'])
            ->where('plans.price_monthly', '>', 0)
            ->distinct()
            ->count('subscriptions.tenant_id');
    }

    /**
     * @param  Collection<int, PlanChangeRequest>  $collected
     * @return list<array<string, mixed>>
     */
    private function monthlyTrend(Collection $collected): array
    {
        $start = now()->startOfMonth();

        return collect(range(self::TREND_MONTHS - 1, 0))
            ->map(function (int $offset) use ($collected, $start): array {
                $month = $start->copy()->subMonths($offset);
                $key = $month->format('Y-m');

                $rows = $collected->filter(function (PlanChangeRequest $request) use ($key): bool {
                    $date = $request->payment_date ?? $request->created_at;

                    return $date instanceof CarbonInterface && $date->format('Y-m') === $key;
                });

                $minor = (int) $rows->sum('amount_minor');

                return [
                    'month' => $key,
                    'label' => $month->format('M Y'),
                    'count' => $rows->count(),
                    'amount_minor' => $minor,
                    'amount' => round($minor / 100, 2),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, int>  $statusCounts
     * @return list<array<string, mixed>>
     */
    private function statusBreakdown(Collection $statusCounts): array
    {
        return collect([
            PlanChangeRequest::STATUS_PENDING,
            PlanChangeRequest::STATUS_INVOICED,
            PlanChangeRequest::STATUS_PAID,
            PlanChangeRequest::STATUS_APPROVED,
            PlanChangeRequest::STATUS_REJECTED,
            PlanChangeRequest::STATUS_CANCELLED,
        ])
            ->map(fn (string $status): array => [
                'status' => $status,
                'count' => (int) ($statusCounts[$status] ?? 0),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, PlanChangeRequest>  $collected
     * @return list<array<string, mixed>>
     */
    private function byPlan(Collection $collected): array
    {
        return $collected
            ->groupBy('plan_code')
            ->map(function (Collection $rows, mixed $code): array {
                $minor = (int) $rows->sum('amount_minor');

                return [
                    'plan_code' => (string) $code,
                    'plan_name' => (string) $rows->first()->plan_name,
                    'count' => $rows->count(),
                    'amount_minor' => $minor,
                    'amount' => round($minor / 100, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PlanChangeRequest>  $collected
     * @return list<array<string, mixed>>
     */
    private function byFarm(Collection $collected): array
    {
        return $collected
            ->groupBy('tenant_id')
            ->map(function (Collection $rows, mixed $tenantId): array {
                $minor = (int) $rows->sum('amount_minor');

                return [
                    'tenant_id' => (int) $tenantId,
                    'tenant_name' => (string) $rows->first()->tenant_name,
                    'count' => $rows->count(),
                    'amount_minor' => $minor,
                    'amount' => round($minor / 100, 2),
                ];
            })
            ->values()
            ->all();
    }
}
