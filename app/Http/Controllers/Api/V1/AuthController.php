<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $login = $request->input('login');
        $password = $request->input('password');

        $credentials = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email' => $login, 'password' => $password]
            : ['phone' => $login, 'password' => $password];

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Invalid email, phone or password.',
                ],
            ], 401);
        }

        $user = Auth::user();
        $expiration = config('sanctum.expiration');

        $token = $user->createToken(
            $request->input('device_name', 'app'),
            ['*'],
            $expiration ? now()->addMinutes((int) $expiration) : null
        )->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['data' => null]);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }
}
