<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PlanRequestService;
use App\Domain\Billing\Services\PlanService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Resources\PlanChangeRequestResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    public function __construct(
        private PlanService $plans,
        private PlanRequestService $requests,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('billing.view');

        return $this->summaryResponse();
    }

    public function usage(): JsonResponse
    {
        $this->authorize('billing.view');

        return $this->summaryResponse();
    }

    public function subscribe(SubscriptionStoreRequest $request): JsonResponse
    {
        $this->authorize('billing.manage');
        $this->plans->subscribe($request->validated()['plan_code']);

        return $this->summaryResponse();
    }

    public function cancel(): JsonResponse
    {
        $this->authorize('billing.manage');
        $this->plans->cancel();

        return $this->summaryResponse();
    }

    private function summaryResponse(): JsonResponse
    {
        $summary = $this->plans->summary();
        $planRequest = $this->requests->activeForTenant($summary['subscription']->tenant_id);

        return response()->json([
            'data' => [
                'plan' => new PlanResource($summary['plan']->setAttribute('current', true)),
                'subscribed_plan' => new PlanResource($summary['subscribed_plan']),
                'subscription' => new SubscriptionResource($summary['subscription']),
                'limits' => $summary['limits'],
                'usage' => $summary['usage'],
                'can_create' => $summary['can_create'],
                'plan_request' => $planRequest === null
                    ? null
                    : new PlanChangeRequestResource($planRequest),
            ],
        ]);
    }
}
