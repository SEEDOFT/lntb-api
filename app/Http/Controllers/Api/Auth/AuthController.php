<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GoogleLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\FcmTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly FcmTokenService $fcmTokens,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated(), $request);

        return ApiResponse::success('Registration completed successfully.', [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => (new UserResource($result['user']->load('status')))->resolve($request),
            'is_new_account' => $result['is_new_account'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated(), $request);

        return ApiResponse::success('Login completed successfully.', [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => (new UserResource($result['user']->load('status')))->resolve($request),
            'is_new_account' => $result['is_new_account'],
        ]);
    }

    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $result = $this->auth->google($request->validated(), $request);

        return ApiResponse::success('Google authentication completed successfully.', [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => (new UserResource($result['user']->load('status')))->resolve($request),
            'is_new_account' => $result['is_new_account'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('Current user retrieved successfully.',
            new UserResource($request->user()->load('status'))->resolve($request)
        );
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $deviceKey = $request->validated('fcm_device_key');
        if (is_string($deviceKey) && $deviceKey !== '') {
            $this->fcmTokens->revoke($request->user(), $deviceKey);
        }

        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Logout completed successfully.');
    }
}
