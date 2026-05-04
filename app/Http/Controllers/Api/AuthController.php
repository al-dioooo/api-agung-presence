<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate a user and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = auth()->user();

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success('Login successful.', [
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('Logged out successfully.');
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success('User profile retrieved.', new UserResource($request->user()));
    }

    /**
     * Register a new user (administrator only).
     */
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->success('User registered successfully.', [
            'user' => new UserResource($user),
        ], 201);
    }
}
