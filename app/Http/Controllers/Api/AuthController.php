<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use RespondsWithJson;

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', (string) $validated['email'])
            ->first();

        if (! $user || ! Hash::check((string) $validated['password'], (string) $user->password)) {
            return $this->errorResponse(
                message: 'Invalid credentials.',
                status: 422,
                errors: ['email' => ['The provided credentials are incorrect.']],
            );
        }

        $tokenName = (string) ($validated['device_name'] ?? 'mobile-app');
        $token = $user->createToken($tokenName, ['mobile'])->plainTextToken;

        return $this->successResponse(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->resolve(),
            ],
            message: 'Login successful.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        } else {
            $user->tokens()->delete();
        }

        return $this->successResponse(
            data: null,
            message: 'Logout successful.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            data: [
                'user' => (new UserResource($user))->resolve(),
            ],
            message: 'Profile fetched successfully.',
        );
    }
}
