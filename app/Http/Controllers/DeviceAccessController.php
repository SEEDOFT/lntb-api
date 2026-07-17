<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GrantDeviceUserRequest;
use App\Http\Resources\DeviceAccessResource;
use App\Models\Device;
use App\Models\DeviceUserAccess;
use App\Services\DeviceAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceAccessController extends Controller
{
    public function __construct(private readonly DeviceAccessService $access) {}

    public function index(Request $request, Device $device): JsonResponse
    {
        $this->authorize('manageAccess', $device);
        $items = $device->accessRecords()->with(['user.status', 'grantedBy.status', 'status'])->latest('granted_at')->get();

        return ApiResponse::success('Device users retrieved successfully.', DeviceAccessResource::collection($items)->resolve($request));
    }

    public function store(GrantDeviceUserRequest $request, Device $device): JsonResponse
    {
        $this->authorize('manageAccess', $device);
        $record = $this->access->grant($device, $request->user(), $request->validated('login'));

        return ApiResponse::success('User access granted successfully.', (new DeviceAccessResource($record))->resolve($request), 201);
    }

    public function destroy(Request $request, Device $device, DeviceUserAccess $access): JsonResponse
    {
        $this->authorize('manageAccess', $device);
        $record = $this->access->revoke($device, $access, $request->user());

        return ApiResponse::success('Shared access revoked successfully.', (new DeviceAccessResource($record))->resolve($request));
    }
}
