<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $login = trim((string) $this->input('login', ''));

        if ($login !== '') {
            if (str_contains($login, '@')) {
                $this->merge(['email' => IdentityNormalizer::email($login)]);
            } elseif (str_starts_with($login, '+855')) {
                $this->merge([
                    'country_code' => '+855',
                    'phone_number' => IdentityNormalizer::phone(substr($login, 4)),
                ]);
            } else {
                $this->merge(['phone_number' => IdentityNormalizer::phone($login)]);
            }
        }

        if (is_string($this->input('phone_number'))) {
            $this->merge(['phone_number' => IdentityNormalizer::phone($this->input('phone_number'))]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => IdentityNormalizer::email($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'login' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'required_with:phone_number', 'string', 'max:5'],
            'phone_number' => ['nullable', 'required_without:email', 'string', 'max:20'],
            'email' => ['nullable', 'required_without:phone_number', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'fcm_device_key' => ['nullable', 'required_with:fcm_token', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
