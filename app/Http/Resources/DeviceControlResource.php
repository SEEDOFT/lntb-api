<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeviceControlResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'device' => $this->whenLoaded('device', fn () => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'mac_address' => $this->device->mac_address,
            ]),
            'requested_by' => new UserResource($this->whenLoaded('user')),
            'status' => $this->whenLoaded('status', fn () => ['code' => $this->status->code, 'name' => $this->status->name]),
            'control_type' => $this->control_type,
            'control_data' => $this->control_data,
            'requested_at' => $this->requested_at,
            'completed_at' => $this->completed_at,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at,
        ];
    }
}
