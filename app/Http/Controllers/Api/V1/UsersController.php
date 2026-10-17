<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('users.view');

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate($request->input('per_page', 25))
            ->appends($request->query());

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

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $role = Role::where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        return response()->json([
            'data' => new UserResource($user->load('roles')),
        ], 201);
    }

    public function show(User $user)
    {
        $this->authorize('users.view', $user);

        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $user->update($data);

        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }

    public function destroy(User $user)
    {
        $this->authorize('users.delete', $user);

        if ($user->id === auth()->id()) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'You cannot delete your own account.',
                ],
            ], 403);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
