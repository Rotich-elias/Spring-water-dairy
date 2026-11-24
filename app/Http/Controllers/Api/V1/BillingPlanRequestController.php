<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Billing\Services\PlanRequestService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequestStoreRequest;
use App\Http\Resources\PlanChangeRequestResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingPlanRequestController extends Controller
{
    public function __construct(
        private PlanRequestService $requests,
        private InvoiceService $invoices,
        private TenantContext $context,
    ) {}

    public function store(PlanRequestStoreRequest $request): JsonResponse
    {
        $this->authorize('billing.manage');

        $data = $request->validated();
        $planRequest = $this->requests->create(
            $this->tenantId(),
            $request->user()?->id,
            $data['plan_code'],
            $data['note'] ?? null,
        );

        return response()->json([
            'data' => new PlanChangeRequestResource($planRequest),
        ], 201);
    }

    public function destroy(int $planRequest): JsonResponse
    {
        $this->authorize('billing.manage');

        $cancelled = $this->requests->cancel($this->tenantId(), $planRequest);

        return response()->json([
            'data' => new PlanChangeRequestResource($cancelled),
        ]);
    }

    public function invoice(int $planRequest): Response
    {
        $this->authorize('billing.view');

        return $this->invoices->render(
            $this->requests->findForTenant($this->tenantId(), $planRequest),
        );
    }

    private function tenantId(): int
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            abort(404, 'No active farm is available for this account.');
        }

        return $tenantId;
    }
}
