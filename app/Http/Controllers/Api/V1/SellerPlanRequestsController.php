<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Billing\Services\PlanRequestService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequestPaidRequest;
use App\Http\Requests\PlanRequestRejectRequest;
use App\Http\Resources\PlanChangeRequestResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SellerPlanRequestsController extends Controller
{
    public function __construct(
        private PlanRequestService $requests,
        private InvoiceService $invoices,
        private TenantContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform('plan_requests.view');

        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in([
                'pending', 'invoiced', 'paid', 'approved', 'rejected', 'cancelled',
            ])],
        ]);

        return response()->json([
            'data' => PlanChangeRequestResource::collection(
                $this->requests->forSeller($filters['status'] ?? null),
            ),
        ]);
    }

    public function invoice(int $planRequest): Response
    {
        $this->authorizePlatform('plan_requests.view');

        return $this->invoices->render($this->requests->findForSeller($planRequest));
    }

    public function issueInvoice(int $planRequest): JsonResponse
    {
        $this->authorizePlatform('plan_requests.manage');

        $issued = $this->requests->issueInvoice($planRequest, $this->adminId());

        return response()->json([
            'data' => new PlanChangeRequestResource($issued),
        ]);
    }

    public function markPaid(PlanRequestPaidRequest $request, int $planRequest): JsonResponse
    {
        $this->authorizePlatform('plan_requests.manage');

        $data = $request->validated();
        $paid = $this->requests->markPaid(
            $planRequest,
            $this->adminId(),
            $data['payment_reference'],
            Carbon::parse($data['payment_date']),
        );

        return response()->json([
            'data' => new PlanChangeRequestResource($paid),
        ]);
    }

    public function approve(int $planRequest): JsonResponse
    {
        $this->authorizePlatform('plan_requests.manage');

        $approved = $this->requests->approve($planRequest, $this->adminId());

        return response()->json([
            'data' => new PlanChangeRequestResource($approved),
        ]);
    }

    public function reject(PlanRequestRejectRequest $request, int $planRequest): JsonResponse
    {
        $this->authorizePlatform('plan_requests.manage');

        $rejected = $this->requests->reject(
            $planRequest,
            $this->adminId(),
            $request->validated()['reason'],
        );

        return response()->json([
            'data' => new PlanChangeRequestResource($rejected),
        ]);
    }

    private function authorizePlatform(string $ability): void
    {
        $this->context->clear();
        $this->authorize('platform.access');
        $this->authorize($ability);
    }

    private function adminId(): int
    {
        return (int) auth()->id();
    }
}
