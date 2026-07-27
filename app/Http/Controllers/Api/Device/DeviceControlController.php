<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Device;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDeviceControlRequest;
use App\Http\Resources\DeviceControlResource;
use App\Models\Device;
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

        return ApiResponse::success('Control history retrieved successfully.', DeviceControlResource::collection($page->getCollection()), meta: [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]);
    }

    public function store(CreateDeviceControlRequest $request, Device $device): JsonResponse
    {
        $this->authorize('control', $device);
        $control = $this->controls->create($device, $request->user(), $request->validated());

        return ApiResponse::success('Control command created successfully.', DeviceControlResource::make($control), 201);
    }

    public function show(Request $request, Device $device, DeviceControl $control): JsonResponse
    {
        $this->authorize('viewHistory', $device);
        if ($control->device_id !== $device->id) {
            throw new BusinessException('CONTROL_NOT_FOUND', 'The control command was not found.', 404);
        }

        return ApiResponse::success('Control command retrieved successfully.', DeviceControlResource::make($control->load(['user.status', 'status'])));
    }
}
