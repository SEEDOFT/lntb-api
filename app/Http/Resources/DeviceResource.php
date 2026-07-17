<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->owner_user_id === $request->user()?->id ? 'owner' : 'shared';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'mac_address' => $this->mac_address,
            'firmware_version' => $this->firmware_version,
            'type' => $this->whenLoaded('type', fn () => ['code' => $this->type->code, 'name' => $this->type->name]),
            'status' => $this->whenLoaded('status', fn () => ['code' => $this->status->code, 'name' => $this->status->name]),
            'access_role' => $role,
            'claimed_at' => $this->claimed_at,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
        ];
    }
}
