<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Models\Impersonation;
use Illuminate\Http\JsonResponse;

class SellerController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): JsonResponse
    {
        // Seller routes are platform-level: a stale tenant from an earlier
        // request must not influence the Spatie permission check.
        $this->context->clear();
        $this->authorize('platform.access');

        return response()->json([
            'data' => [
                'farms' => Tenant::query()->count(),
                'active_subscriptions' => Subscription::query()->where('status', 'active')->count(),
                'plans' => Plan::query()->count(),
                'new_inquiries' => ContactInquiry::query()->where('status', 'new')->count(),
                'impersonations' => Impersonation::query()->count(),
            ],
        ]);
    }
}
