<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PaymentHistoryService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentHistoryIndexRequest;
use App\Http\Resources\PlanChangeRequestResource;
use Illuminate\Http\JsonResponse;

class BillingPaymentsController extends Controller
{
    public function __construct(
        private PaymentHistoryService $payments,
        private TenantContext $context,
    ) {}

    public function index(PaymentHistoryIndexRequest $request): JsonResponse
    {
        $this->authorize('billing.view');

        $tenantId = $this->context->id();

        if ($tenantId === null) {
            abort(404, 'No active farm is available for this account.');
        }

        $result = $this->payments->forTenant($tenantId, $request->filters());
        $paginator = $result['paginator'];

        return response()->json([
            'data' => PlanChangeRequestResource::collection($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'statistics' => $result['statistics'],
        ]);
    }
}
