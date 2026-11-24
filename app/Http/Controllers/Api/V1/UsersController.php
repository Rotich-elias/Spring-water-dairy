<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Services\PlanService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private PlanService $plans,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('users.view');

        $tenantId = $this->tenantId();
        $pivotRoles = DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->pluck('role', 'user_id');

        $users = User::query()
            ->whereIn('users.id', DB::table('tenant_user')
                ->where('tenant_id', $tenantId)
                ->select('user_id'))
            ->with('roles')
            ->orderBy('name')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

        $users->each(function (User $user) use ($pivotRoles): void {
            $user->setAttribute('tenant_role', $pivotRoles->get($user->id));
        });

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();
        $roleName = $data['role'];
        unset($data['role']);

        $this->plans->assertCan('users');

        $tenantId = $this->tenantId();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $role = Role::where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => $roleName,
            'is_owner' => false,
        ]);

        if (blank($user->current_tenant_id)) {
            $user->forceFill(['current_tenant_id' => $tenantId])->save();
        }

        $user->setAttribute('tenant_role', $roleName);

        return response()->json([
            'data' => new UserResource($user->load('roles')),
        ], 201);
    }

    public function show(User $user)
    {
        $this->assertTenantMember($user);
        $this->authorize('users.view', $user);

        return response()->json(['data' => new UserResource($this->withTenantRole($user)->load('roles'))]);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $this->assertTenantMember($user);
        $data = $request->validated();

        if (isset($data['role'])) {
            if ($this->isLastOwner($user, $data['role'])) {
                return response()->json([
                    'error' => [
                        'code' => 'validation_failed',
                        'message' => 'A tenant must keep at least one Owner.',
                        'fields' => ['role' => ['The last Owner cannot be removed.']],
                    ],
                ], 422);
            }

            DB::table('tenant_user')
                ->where('tenant_id', $this->tenantId())
                ->where('user_id', $user->id)
                ->update(['role' => $data['role']]);

            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $user->update($data);

        return response()->json(['data' => new UserResource($this->withTenantRole($user)->load('roles'))]);
    }

    public function destroy(User $user)
    {
        $this->assertTenantMember($user);
        $this->authorize('users.delete', $user);

        if ($user->id === auth()->id()) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'You cannot delete your own account.',
                ],
            ], 403);
        }

        DB::table('tenant_user')
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->delete();

        if (! DB::table('tenant_user')->where('user_id', $user->id)->exists()) {
            $user->delete();
        }

        return response()->json(null, 204);
    }

    private function tenantId(): int
    {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            abort(404);
        }

        return $tenantId;
    }

    private function assertTenantMember(User $user): void
    {
        $exists = DB::table('tenant_user')
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->exists();

        if (! $exists) {
            abort(404);
        }
    }

    private function withTenantRole(User $user): User
    {
        $role = DB::table('tenant_user')
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->value('role');

        return $user->setAttribute('tenant_role', $role);
    }

    private function isLastOwner(User $user, string $newRole): bool
    {
        $membership = DB::table('tenant_user')
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $user->id)
            ->first();

        if ($membership?->role !== 'Owner' || $newRole === 'Owner') {
            return false;
        }

        return DB::table('tenant_user')
            ->where('tenant_id', $this->tenantId())
            ->where('role', 'Owner')
            ->count() <= 1;
    }
}
