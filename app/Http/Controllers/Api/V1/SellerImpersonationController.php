<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImpersonationStoreRequest;
use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class SellerImpersonationController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function store(ImpersonationStoreRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorizePlatform('impersonate');

        $data = $request->validated();

        if (isset($data['user_id'])) {
            $target = User::query()->findOrFail($data['user_id']);
        } else {
            $ownerId = DB::table('tenant_user')
                ->where('tenant_id', $tenant->id)
                ->where('is_owner', true)
                ->value('user_id');

            $target = $ownerId !== null
                ? User::query()->findOrFail($ownerId)
                : $tenant->owner;
        }

        if (! $target instanceof User) {
            abort(404, 'No farm owner is available for this farm.');
        }

        if ($target->hasRole('Platform Admin')) {
            throw new AuthorizationException('Platform administrators cannot be impersonated.');
        }

        $membership = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->first();

        if (! $membership) {
            abort(404, 'The selected user is not a member of this farm.');
        }

        if ($membership->role === 'Platform Admin') {
            throw new AuthorizationException('Platform administrators cannot be impersonated.');
        }

        $expiresAt = now()->addMinutes(30);

        [$impersonation, $accessToken] = DB::transaction(function () use (
            $request,
            $tenant,
            $target,
            $expiresAt
        ): array {
            $impersonation = Impersonation::create([
                'impersonator_id' => $request->user()->id,
                'user_id' => $target->id,
                'tenant_id' => $tenant->id,
                'reason' => $request->validated()['reason'],
                'expires_at' => $expiresAt,
            ]);

            $accessToken = $target->createToken(
                'impersonation:'.$impersonation->id,
                ['*'],
                $expiresAt
            );

            $impersonation->forceFill([
                'token_id' => $accessToken->accessToken->getKey(),
            ])->save();

            return [$impersonation, $accessToken];
        });

        return response()->json([
            'data' => [
                'token' => $accessToken->plainTextToken,
                'expires_at' => $impersonation->expires_at?->toISOString(),
                'impersonation_id' => $impersonation->id,
                'user' => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'email' => $target->email,
                    'role' => filled($membership->role) ? $membership->role : $target->getRoleNames()->first(),
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
            ],
        ], 201);
    }

    public function index(): JsonResponse
    {
        $this->authorizePlatform('impersonate');

        $this->revokeExpiredSessions();

        $impersonations = Impersonation::query()
            ->with([
                'impersonator:id,name,email',
                'user:id,name,email',
                'tenant:id,name,slug',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $impersonations->map(function (Impersonation $impersonation): array {
                $target = $impersonation->user;

                return [
                    'id' => $impersonation->id,
                    'impersonator_id' => $impersonation->impersonator_id,
                    'impersonator' => $this->userSummary($impersonation->impersonator),
                    'user_id' => $impersonation->user_id,
                    'user' => $this->userSummary($target),
                    'target' => $this->userSummary($target),
                    'tenant_id' => $impersonation->tenant_id,
                    'tenant' => [
                        'id' => $impersonation->tenant?->id,
                        'name' => $impersonation->tenant?->name,
                        'slug' => $impersonation->tenant?->slug,
                    ],
                    'reason' => $impersonation->reason,
                    'started_at' => $impersonation->created_at?->toISOString(),
                    'created_at' => $impersonation->created_at?->toISOString(),
                    'ended_at' => $impersonation->ended_at?->toISOString(),
                    'expires_at' => $impersonation->expires_at?->toISOString(),
                ];
            })->all(),
        ]);
    }

    public function end(Request $request, Impersonation $impersonation): JsonResponse
    {
        $this->authorizePlatform('impersonate');

        if ($impersonation->impersonator_id !== $request->user()->id
            && ! $request->user()->hasRole('Platform Admin')) {
            throw new AuthorizationException('Only the impersonator or a platform admin can end this session.');
        }

        if ($impersonation->token_id !== null) {
            PersonalAccessToken::query()->whereKey($impersonation->token_id)->delete();
        }

        $impersonation->forceFill(['ended_at' => now()])->save();

        return response()->json(['data' => null]);
    }

    private function authorizePlatform(string $ability): void
    {
        $this->context->clear();
        $this->authorize('platform.access');
        $this->authorize($ability);
    }

    /**
     * Expired sessions are closed lazily when history is inspected so their
     * revoked token and ended_at stay consistent.
     */
    private function revokeExpiredSessions(): void
    {
        Impersonation::query()
            ->whereNull('ended_at')
            ->whereNotNull('token_id')
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (Impersonation $impersonation): void {
                PersonalAccessToken::query()->whereKey($impersonation->token_id)->delete();
                $impersonation->forceFill(['ended_at' => $impersonation->expires_at])->save();
            });
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
