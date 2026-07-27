<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve();
        } elseif ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

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

    public static function error(string $message, int $status = 500, ?array $errors = null): JsonResponse
    {
        $payload = [
            'status' => [
                'code' => $status,
                'success' => false,
                'message' => $message,
            ],
            'data' => $errors ?? (object) [],
        ];

        return response()->json($payload, $status);
    }
}
