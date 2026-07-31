<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceAccessStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class DeviceService
{
    public function accessible(User $user): Collection
    {
        return Device::query()
            ->with(['type', 'status'])
            ->where(function ($query) use ($user): void {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('accessRecords', function ($access) use ($user): void {
                        $access->where('user_id', $user->id)
                            ->whereHas('status', fn ($status) => $status->where('code', DeviceAccessStatus::ACTIVE));
                    });
            })->orderBy('name')->orderBy('id')->get();
    }
}
