<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Requests\CreateDeviceControlRequest;
use App\Http\Resources\DeviceControlResource;
use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\DeviceControl;
use App\Services\DeviceControlService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceControlController extends Controller
{
    public function __construct(private readonly DeviceControlService $controls) {}

    public function index(Request $request, Device $device): JsonResponse
    {
        $this->authorize('viewHistory', $device);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $page = $device->controls()->with(['user.status', 'status'])->latest('requested_at')->paginate($perPage);

        return ApiResponse::success('Control history retrieved successfully.', DeviceControlResource::collection($page->getCollection())->resolve($request), meta: [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $page = DeviceControl::query()
            ->with(['device.type', 'device.status', 'user.status', 'status'])
            ->whereHas('device', function ($devices) use ($userId): void {
                $devices->where('owner_user_id', $userId)
                    ->orWhereHas('accessRecords', function ($access) use ($userId): void {
                        $access->where('user_id', $userId)
                            ->whereHas('status', fn ($status) => $status->where('code', DeviceAccessStatus::ACTIVE));
                    });
            })
            ->latest('requested_at')
            ->paginate($perPage);

        return ApiResponse::success(
            'Control history retrieved successfully.',
            DeviceControlResource::collection($page->getCollection())->resolve($request),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function store(CreateDeviceControlRequest $request, Device $device): JsonResponse
    {
        $this->authorize('control', $device);
        $control = $this->controls->create($device, $request->user(), $request->validated());

        return ApiResponse::success('Control command created successfully.', (new DeviceControlResource($control))->resolve($request), 201);
    }

    public function show(Request $request, Device $device, DeviceControl $control): JsonResponse
    {
        $this->authorize('viewHistory', $device);
        if ($control->device_id !== $device->id) {
            throw new BusinessException('CONTROL_NOT_FOUND', 'The control command was not found.', 404);
        }

        return ApiResponse::success('Control command retrieved successfully.', (new DeviceControlResource($control->load(['user.status', 'status'])))->resolve($request));
    }
}
