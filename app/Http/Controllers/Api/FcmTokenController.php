<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevokeFcmTokenRequest;
use App\Http\Requests\SyncFcmTokenRequest;
use App\Services\FcmTokenService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class FcmTokenController extends Controller
{
    public function __construct(
        private readonly FcmTokenService $tokens,
    ) {}

    public function update(SyncFcmTokenRequest $request): JsonResponse
    {
        $this->tokens->syncFromPayload($request->user(), $request->validated());

        return ApiResponse::success('FCM token updated successfully.');
    }

    public function destroy(RevokeFcmTokenRequest $request): JsonResponse
    {
        $this->tokens->revoke(
            $request->user(),
            $request->validated('fcm_device_key'),
        );

        return ApiResponse::success('FCM token revoked successfully.');
    }
}
