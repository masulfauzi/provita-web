<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Token Sanctum untuk aplikasi nasabah (mobile). Belum dipakai panel web.
 */
class TokenController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string', 'max:100']]);

        $user = $request->validateCredentials();

        return response()->json([
            'token' => $user->createToken($request->string('device_name'))->plainTextToken,
            'user' => $user->only('id', 'username', 'name'),
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
