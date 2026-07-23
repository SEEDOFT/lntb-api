<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\DB;

final class FcmTokenService
{
    public function __construct(
        private readonly NotificationDeliveryService $deliveries,
    ) {}

    /** @param array<string, mixed> $data */
    public function syncFromPayload(User $user, array $data): ?UserFcmToken
    {
        $token = trim((string) ($data['fcm_token'] ?? ''));
        $deviceKey = trim((string) ($data['fcm_device_key'] ?? ''));

        if ($token === '' || $deviceKey === '') {
            return null;
        }

        return $this->sync(
            user: $user,
            deviceKey: $deviceKey,
            fcmToken: $token,
            platform: $data['platform'] ?? null,
            deviceName: $data['device_name'] ?? null,
            appVersion: $data['app_version'] ?? null,
        );
    }

    public function sync(
        User $user,
        string $deviceKey,
        string $fcmToken,
        ?string $platform = null,
        ?string $deviceName = null,
        ?string $appVersion = null,
    ): UserFcmToken {
        [$registration, $tokenChanged] = DB::transaction(function () use (
            $user,
            $deviceKey,
            $fcmToken,
            $platform,
            $deviceName,
            $appVersion,
        ): array {
            UserFcmToken::query()
                ->where('fcm_token', $fcmToken)
                ->where('device_key', '!=', $deviceKey)
                ->update([
                    'fcm_token' => null,
                    'revoked_at' => now(),
                ]);

            $registration = UserFcmToken::query()
                ->lockForUpdate()
                ->firstOrNew(['device_key' => $deviceKey]);
            $tokenChanged = $registration->fcm_token !== $fcmToken;

            $registration->forceFill([
                'user_id' => $user->id,
                'fcm_token' => $fcmToken,
                'platform' => $platform,
                'device_name' => $deviceName,
                'app_version' => $appVersion,
                'last_used_at' => now(),
                'revoked_at' => null,
            ])->save();

            return [$registration, $tokenChanged];
        });

        $welcome = Notification::query()
            ->where('user_id', $user->id)
            ->where('deduplication_key', "welcome:user:{$user->id}")
            ->whereNull('push_sent_at')
            ->first();

        if ($welcome !== null) {
            $this->deliveries->createForToken($welcome, $registration, $tokenChanged);
        }

        return $registration;
    }

    public function revoke(User $user, string $deviceKey): void
    {
        UserFcmToken::query()
            ->where('user_id', $user->id)
            ->where('device_key', $deviceKey)
            ->update([
                'fcm_token' => null,
                'revoked_at' => now(),
            ]);
    }
}
