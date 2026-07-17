<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\User;

final class DevicePolicy
{
    public function view(User $user, Device $device): bool
    {
        return $this->isOwner($user, $device) || $device->accessRecords()
            ->where('user_id', $user->id)
            ->whereHas('status', fn ($query) => $query->where('code', DeviceAccessStatus::ACTIVE))
            ->exists();
    }

    public function manageAccess(User $user, Device $device): bool
    {
        return $this->isOwner($user, $device);
    }

    public function control(User $user, Device $device): bool
    {
        return $this->view($user, $device);
    }

    public function viewHistory(User $user, Device $device): bool
    {
        return $this->view($user, $device);
    }

    private function isOwner(User $user, Device $device): bool
    {
        return $device->owner_user_id === $user->id;
    }
}
