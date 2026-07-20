<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone_number'))) {
            $this->merge(['phone_number' => IdentityNormalizer::phone($this->input('phone_number'))]);
        }
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'max:5'],
            'phone_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
