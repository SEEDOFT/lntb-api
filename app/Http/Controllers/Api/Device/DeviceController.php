<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $devices) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Devices retrieved successfully.', DeviceResource::collection($this->devices->accessible($request->user())));
    }

    public function claim(ClaimDeviceRequest $request): JsonResponse
    {
        $device = $this->devices->claim($request->user(), $request->validated());

        return ApiResponse::success('Device claimed successfully.', DeviceResource::make($device));
    }

    public function show(Request $request, Device $device): JsonResponse
    {
        $this->authorize('view', $device);

        return ApiResponse::success('Device retrieved successfully.', DeviceResource::make($device->load(['type', 'status'])));
    }
}
