<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = ['message' => $message, 'data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(string $message, string $code, int $status, ?array $errors = null): JsonResponse
    {
        $payload = [
            'message' => $message,
            'code' => $code,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
