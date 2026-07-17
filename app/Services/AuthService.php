<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class AuthService
{
    public function register(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'phone_number' => $data['phone_number'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE),
            ]);

            return $this->issueToken($user, $request);
        });
    }

    public function login(array $data, Request $request): array
    {
        $field = str_contains($data['login'], '@') ? 'email' : 'phone_number';
        $user = User::query()->where($field, $data['login'])->first();
        if ($user === null || $user->password === null || ! Hash::check($data['password'], $user->password)) {
            throw new BusinessException('INVALID_CREDENTIALS', 'The supplied credentials are invalid.', 401);
        }
        $this->ensureActive($user);

        return DB::transaction(function () use ($user, $request): array {
            $user->forceFill(['last_login_at' => now()])->save();

            return $this->issueToken($user, $request);
        });
    }

    public function google(array $data, Request $request): array
    {
        try {
            $google = Socialite::driver('google')->userFromToken($data['access_token']);
        } catch (Throwable) {
            throw new BusinessException('INVALID_GOOGLE_TOKEN', 'The Google access token is invalid.', 401);
        }

        $googleId = (string) $google->getId();
        $email = $google->getEmail() !== null ? strtolower(trim($google->getEmail())) : null;
        $verified = $this->isVerifiedGoogleEmail($google);

        return DB::transaction(function () use ($google, $googleId, $email, $verified, $request): array {
            $user = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
            if ($user === null && $email !== null) {
                $user = User::query()->where('email', $email)->lockForUpdate()->first();
                if ($user !== null && ! $verified) {
                    throw new BusinessException('GOOGLE_EMAIL_NOT_VERIFIED', 'The Google email must be verified before linking.', 409);
                }
                if ($user?->google_id !== null && $user->google_id !== $googleId) {
                    throw new BusinessException('GOOGLE_ACCOUNT_CONFLICT', 'The email is linked to another Google account.', 409);
                }
            }

            if ($user === null) {
                if ($email === null || ! $verified) {
                    throw new BusinessException('GOOGLE_EMAIL_REQUIRED', 'A verified Google email is required.', 409);
                }
                $user = User::query()->create([
                    'name' => $google->getName() ?: $email,
                    'email' => $email,
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                    'user_status_id' => UserStatus::resolveId(UserStatus::ACTIVE),
                ]);
            } elseif ($user->google_id === null) {
                $user->forceFill(['google_id' => $googleId, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            }

            $this->ensureActive($user);
            $user->forceFill(['last_login_at' => now()])->save();

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

    private function isVerifiedGoogleEmail(\Laravel\Socialite\Contracts\User $user): bool
    {
        $raw = $user->getRaw();

        return filter_var($raw['verified_email'] ?? $raw['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
    }
}
