<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AuthService
{
    public function register(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'country_code' => $data['country_code'],
                'phone_number' => $data['phone_number'],
                'password' => $data['password'],
                'fcm_token' => $data['fcm_token'] ?? null,
                'user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE),
            ]);

            event(new \Illuminate\Auth\Events\Registered($user));

            return $this->issueToken($user, $request);
        });
    }

    public function login(array $data, Request $request): array
    {
        $user = User::query()
            ->where('country_code', $data['country_code'])
            ->where('phone_number', $data['phone_number'])
            ->first();

        if ($user === null || $user->password === null || ! Hash::check($data['password'], $user->password)) {
            throw new BusinessException('INVALID_CREDENTIALS', 'The supplied credentials are invalid.', 401);
        }
        $this->ensureActive($user);

        return DB::transaction(function () use ($user, $data, $request): array {
            $updates = ['last_login_at' => now()];
            if (isset($data['fcm_token'])) {
                $updates['fcm_token'] = $data['fcm_token'];
            }
            $user->forceFill($updates)->save();

            return $this->issueToken($user, $request);
        });
    }

    public function google(array $data, Request $request): array
    {
        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['token'],
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to fetch user from Google tokeninfo.');
            }

            $googleUser = $response->json();

            // Validate Audience
            $allowedAudiences = [
                config('services.google.client_id'),
                config('services.google.ios_client_id'),
            ];

            if (! in_array($googleUser['aud'] ?? '', $allowedAudiences)) {
                $receivedAud = $googleUser['aud'] ?? 'null';
                throw new BusinessException('INVALID_GOOGLE_TOKEN', 'The Google token audience is invalid. Received: ' . $receivedAud, 401);
            }

            if (! isset($googleUser['sub'])) {
                throw new \Exception('Google user payload missing sub.');
            }

            $googleId = (string) $googleUser['sub'];
            $name = $googleUser['name'] ?? explode('@', $googleUser['email'] ?? 'Google User')[0];
        } catch (BusinessException $e) {
            throw $e;
        } catch (Throwable) {
            throw new BusinessException('INVALID_GOOGLE_TOKEN', 'The Google token is invalid.', 401);
        }

        return DB::transaction(function () use ($googleId, $name, $data, $request): array {
            $user = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
            $isNew = false;

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name,
                    'google_id' => $googleId,
                    'country_code' => '+855',
                    'user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE),
                ]);
                $isNew = true;
            }

            $this->ensureActive($user);
            
            $updates = ['last_login_at' => now()];
            if (isset($data['fcm_token'])) {
                $updates['fcm_token'] = $data['fcm_token'];
            }
            $user->forceFill($updates)->save();

            if ($isNew) {
                event(new \Illuminate\Auth\Events\Registered($user));
            }

            return $this->issueToken($user, $request);
        });
    }

    public function ensureActive(User $user): void
    {
        $activeId = UserStatus::resolveId(UserStatus::ACTIVE);
        if ($user->user_status_id !== $activeId) {
            throw new BusinessException('ACCOUNT_NOT_ACTIVE', 'The account is not active.', 403);
        }
    }

    private function issueToken(User $user, Request $request): array
    {
        $deviceName = $request->input('device_name', $request->input('platform', 'mobile'));
        $expiresAt = now()->addDays(30);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }
}
