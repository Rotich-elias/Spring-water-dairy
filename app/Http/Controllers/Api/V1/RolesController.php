<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('users.view');

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $data = $roles->map(fn (Role $role) => [
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->pluck('name')->sort()->all(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => 1,
                'per_page' => $data->count(),
                'total' => $data->count(),
                'last_page' => 1,
            ],
        ]);
    }
}
