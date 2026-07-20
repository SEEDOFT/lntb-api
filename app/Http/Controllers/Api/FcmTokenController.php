<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FcmTokenController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if ($user !== null) {
            $user->forceFill(['fcm_token' => $validated['fcm_token']])->save();
        }

        return response()->json([
            'message' => 'FCM token updated successfully.',
        ]);
    }
}
