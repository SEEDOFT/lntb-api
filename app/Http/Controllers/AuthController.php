<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GoogleLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->authResponse($this->auth->register($request->validated(), $request), 'Registration completed successfully.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authResponse($this->auth->login($request->validated(), $request), 'Login completed successfully.');
    }

    public function google(GoogleLoginRequest $request): JsonResponse
    {
        return $this->authResponse($this->auth->google($request->validated(), $request), 'Google authentication completed successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('Current user retrieved successfully.', (new UserResource($request->user()->load('status')))->resolve($request));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Logout completed successfully.');
    }

    private function authResponse(array $result, string $message, int $status = 200): JsonResponse
    {
        $request = request();

        return ApiResponse::success($message, [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user' => (new UserResource($result['user']->load('status')))->resolve($request),
        ], $status);
    }
}
