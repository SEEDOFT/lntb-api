<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Accept a compound `login` field for convenience
        $login = trim((string) $this->input('login', ''));

        if ($login !== '' && ! str_contains($login, '@')) {
            // Try to extract country_code from +XX prefix
            if (preg_match('/^(\+\d{1,5})(\d{7,14})$/', $login, $m)) {
                $this->merge([
                    'country_code' => $m[1],
                    'phone_number' => $m[2],
                ]);
            } else {
                $this->merge(['phone_number' => $login]);
            }
        }

        if (is_string($this->input('phone_number'))) {
            $this->merge(['phone_number' => preg_replace('/[^0-9]/', '', $this->input('phone_number'))]);
        }
    }

    public function rules(): array
    {
        return [
            'login' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required_without:login', 'string', 'max:5'],
            'phone_number' => ['required_without:login', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'fcm_device_key' => ['nullable', 'required_with:fcm_token', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
