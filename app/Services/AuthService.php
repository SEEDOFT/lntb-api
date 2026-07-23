<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AuthService
{
    public function __construct(
        private readonly FcmTokenService $fcmTokens,
    ) {}

    public function register(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'country_code' => $data['country_code'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'user_status_id' => UserStatus::ID_ACTIVE,
            ]);

            $this->fcmTokens->syncFromPayload($user, $data);
            event(new Registered($user));

            return $this->issueToken($user, $request, true);
        });
    }

    public function login(array $data, Request $request): array
    {
        $user = isset($data['email'])
            ? User::query()->where('email', $data['email'])->first()
            : User::query()
                ->where('country_code', $data['country_code'])
                ->where('phone_number', $data['phone_number'])
                ->first();

        if ($user === null || $user->password === null || ! Hash::check($data['password'], $user->password)) {
            throw new BusinessException('INVALID_CREDENTIALS', 'The supplied credentials are invalid.', 401);
        }
        $this->ensureActive($user);

        return DB::transaction(function () use ($user, $data, $request): array {
            $updates = ['last_login_at' => now()];
            $user->forceFill($updates)->save();
            $this->fcmTokens->syncFromPayload($user, $data);

            return $this->issueToken($user, $request, false);
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
                throw new BusinessException('INVALID_GOOGLE_TOKEN', 'The Google token audience is invalid. Received: '.$receivedAud, 401);
            }

            if (! isset($googleUser['sub'])) {
                throw new \Exception('Google user payload missing sub.');
            }

            $googleId = (string) $googleUser['sub'];
            $email = isset($googleUser['email']) ? strtolower((string) $googleUser['email']) : null;
            $emailVerified = filter_var($googleUser['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
            $name = $googleUser['name'] ?? explode('@', $googleUser['email'] ?? 'Google User')[0];
        } catch (BusinessException $e) {
            throw $e;
        } catch (Throwable) {
            throw new BusinessException('INVALID_GOOGLE_TOKEN', 'The Google token is invalid.', 401);
        }

        return DB::transaction(function () use ($googleId, $email, $emailVerified, $name, $data, $request): array {
            $user = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
            $isNew = false;

            if ($user === null && $email !== null && $emailVerified) {
                $user = User::query()->where('email', $email)->lockForUpdate()->first();
            }

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name,
                    'google_id' => $googleId,
                    'email' => $emailVerified ? $email : null,
                    'email_verified_at' => $emailVerified ? now() : null,
                    'user_status_id' => UserStatus::ID_ACTIVE,
                ]);
                $isNew = true;
            } elseif ($user->google_id !== null && $user->google_id !== $googleId) {
                throw new BusinessException(
                    'GOOGLE_ACCOUNT_CONFLICT',
                    'This email address is already linked to another Google account.',
                    409,
                );
            } elseif ($user->google_id === null) {
                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => $emailVerified ? now() : $user->email_verified_at,
                ])->save();
            }

            $this->ensureActive($user);

            $updates = ['last_login_at' => now()];
            $user->forceFill($updates)->save();
            $this->fcmTokens->syncFromPayload($user, $data);

            if ($isNew) {
                event(new Registered($user));
            }

            return $this->issueToken($user, $request, $isNew);
        });
    }

    public function ensureActive(User $user): void
    {
        $activeId = UserStatus::ID_ACTIVE;
        if ($user->user_status_id !== $activeId) {
            throw new BusinessException('ACCOUNT_NOT_ACTIVE', 'The account is not active.', 403);
        }
    }

    private function issueToken(User $user, Request $request, bool $isNewAccount): array
    {
        $deviceName = $request->input('device_name', $request->input('platform', 'mobile'));
        $expiresAt = now()->addDays(30);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'is_new_account' => $isNewAccount,
        ];
    }
}
