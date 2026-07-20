<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'status' => [
                'code' => $status,
                'success' => true,
                'message' => $message,
            ],
            'data' => $data ?? (object) [],
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(string $message, string $code, int $status, ?array $errors = null): JsonResponse
    {
        $payload = [
            'status' => [
                'code' => $status,
                'success' => false,
                'message' => $message,
                'error_code' => $code, // Keeping the custom string code for client-side parsing
            ],
            'data' => $errors ?? (object) [],
        ];

        return response()->json($payload, $status);
    }
}
