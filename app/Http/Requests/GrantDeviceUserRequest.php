<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GrantDeviceUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('login'))) {
            $login = $this->input('login');
            $this->merge([
                'login' => str_contains($login, '@')
                    ? strtolower(trim($login))
                    : preg_replace('/[^0-9+]/', '', $login),
            ]);
        }
    }

    public function rules(): array
    {
        return ['login' => ['required', 'string', 'max:255']];
    }
}
