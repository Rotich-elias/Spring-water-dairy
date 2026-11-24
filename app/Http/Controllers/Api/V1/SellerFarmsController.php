<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PlanService;
use App\Domain\Milk\Models\MilkRecord;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmProvisionRequest;
use App\Http\Requests\FarmUpdateRequest;
use App\Http\Requests\SubscriptionAssignRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\TenantResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SellerFarmsController extends Controller
{
    public function __construct(
        private TenantService $tenants,
        private PlanService $plans,
        private TenantContext $context,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorizePlatform('tenants.view');

        $tenants = Tenant::query()->with('owner')->orderBy('name')->get();
        $subscriptions = Subscription::query()
            ->with('plan')
            ->whereIn('tenant_id', $tenants->modelKeys())
            ->get()
            ->keyBy('tenant_id');

        $previous = $this->context->tenant();

        try {
            $farms = $tenants->map(function (Tenant $tenant) use ($subscriptions): array {
                $this->context->set($tenant);

                $subscription = $subscriptions->get($tenant->id);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'owner_id' => $tenant->owner_id,
                    'owner_name' => $tenant->owner?->name,
                    'owner_email' => $tenant->owner?->email,
                    'plan_code' => $subscription?->plan?->code,
                    'plan_name' => $subscription?->plan?->name,
                    'subscription_status' => $subscription?->status,
                    'usage' => [
                        'animals' => $this->plans->usage('animals'),
                        'users' => $this->plans->usage('users'),
                        // PlanService reports monthly milk volume; the seller
                        // console reports the tenant's full record count.
                        'milk_records' => MilkRecord::query()->count(),
                        'alert_rules' => $this->plans->usage('alert_rules'),
                    ],
                    'created_at' => $tenant->created_at?->toISOString(),
                ];
            })->all();
        } finally {
            if ($previous) {
                $this->context->set($previous);
            } else {
                $this->context->clear();
            }
        }

        return response()->json(['data' => $farms]);
    }

    public function store(FarmProvisionRequest $request): JsonResponse
    {
        $this->authorizePlatform('farms.provision');

        $data = $request->validated();
        $owner = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['owner_email'])])
            ->first();

        $temporaryPassword = null;

        if ($owner === null) {
            $temporaryPassword = Str::password(16);

            // Provisioning happens outside a farm context, so the usual
            // tenant-scoped user audit event cannot run here.
            $owner = User::withoutEvents(fn (): User => User::create([
                'name' => Str::headline(Str::before($data['owner_email'], '@')),
                'email' => $data['owner_email'],
                'password' => Hash::make($temporaryPassword),
                'role' => 'Owner',
            ]));
        }

        $tenant = $this->tenants->provisionFor($data, $owner);

        if (isset($data['plan_code'])) {
            $this->plans->subscribeForTenant($tenant, $data['plan_code']);
        }

        $response = ['data' => new TenantResource($tenant->setAttribute('current', false))];

        if ($temporaryPassword !== null) {
            $response['temporary_password'] = $temporaryPassword;
        }

        return response()->json($response, 201);
    }

    public function update(FarmUpdateRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorizePlatform('tenants.update');

        $tenant->fill($request->validated());
        $tenant->save();

        return response()->json([
            'data' => new TenantResource($tenant->setAttribute('current', false)),
        ]);
    }

    public function subscribe(SubscriptionAssignRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorizePlatform('subscriptions.manage');

        $subscription = $this->plans->subscribeForTenant(
            $tenant,
            $request->validated()['plan_code']
        );

        return response()->json([
            'data' => new SubscriptionResource($subscription->load('plan')),
        ]);
    }

    public function cancel(Tenant $tenant): JsonResponse
    {
        $this->authorizePlatform('subscriptions.manage');

        $previous = $this->context->tenant();
        $this->context->set($tenant);

        try {
            $subscription = $this->plans->cancel();
        } finally {
            if ($previous) {
                $this->context->set($previous);
            } else {
                $this->context->clear();
            }
        }

        return response()->json([
            'data' => new SubscriptionResource($subscription->load('plan')),
        ]);
    }

    private function authorizePlatform(string $ability): void
    {
        $this->context->clear();
        $this->authorize('platform.access');
        $this->authorize($ability);
    }
}
