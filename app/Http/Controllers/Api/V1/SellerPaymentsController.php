<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PaymentHistoryService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentHistoryIndexRequest;
use App\Http\Resources\PlanChangeRequestResource;
use Illuminate\Http\JsonResponse;

class SellerPaymentsController extends Controller
{
    public function __construct(
        private PaymentHistoryService $payments,
        private TenantContext $context,
    ) {}

    public function index(PaymentHistoryIndexRequest $request): JsonResponse
    {
        $this->context->clear();
        $this->authorize('platform.access');
        $this->authorize('plan_requests.view');

        $result = $this->payments->forSeller($request->filters());
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
