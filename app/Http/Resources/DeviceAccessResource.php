<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeviceAccessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'granted_by' => new UserResource($this->whenLoaded('grantedBy')),
            'status' => $this->whenLoaded('status', fn () => ['code' => $this->status->code, 'name' => $this->status->name]),
            'granted_at' => $this->granted_at,
            'revoked_at' => $this->revoked_at,
        ];
    }
}
