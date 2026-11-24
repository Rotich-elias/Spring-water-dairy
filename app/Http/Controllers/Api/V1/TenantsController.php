<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PlanService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantStoreRequest;
use App\Http\Requests\TenantUpdateRequest;
use App\Http\Resources\TenantResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantsController extends Controller
{
    public function __construct(
        private TenantService $tenants,
        private PlanService $plans,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $pivots = DB::table('tenant_user')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('tenant_id');

        $tenants = Tenant::query()
            ->whereIn('id', $pivots->keys())
            ->orderBy('name')
            ->get()
            ->each(function (Tenant $tenant) use ($pivots, $user): void {
                $tenant->setAttribute('pivot', $pivots->get($tenant->id));
                $tenant->setAttribute('current', (int) $user->current_tenant_id === (int) $tenant->id);
            });

        return response()->json(['data' => TenantResource::collection($tenants)]);
    }

    public function store(TenantStoreRequest $request)
    {
        $data = $request->validated();
        $planCode = $data['plan_code'] ?? null;

        $tenant = $this->tenants->create($data, $request->user());

        if ($planCode !== null) {
            $this->plans->subscribeForTenant($tenant, $planCode);
        }

        $tenant->setAttribute('current', false);

        return response()->json(['data' => new TenantResource($tenant)], 201);
    }

    public function show(Tenant $tenant)
    {
        $this->authorize('tenants.view');
        $this->assertMember($tenant);

        return response()->json(['data' => new TenantResource($tenant)]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant)
    {
        $this->authorize('tenants.update');
        $this->assertMember($tenant);

        $tenant->fill($request->validated());
        $tenant->save();

        return response()->json(['data' => new TenantResource($tenant)]);
    }

    public function switch(Request $request, Tenant $tenant)
    {
        $this->assertMember($tenant);
        $this->tenants->switch($request->user(), $tenant);

        $tenant->setAttribute('current', true);

        return (new TenantResource($tenant))->additional(['reload' => true])->response();
    }

    private function assertMember(Tenant $tenant): void
    {
        $exists = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', auth()->id())
            ->exists();

        if (! $exists) {
            abort(404);
        }
    }
}
