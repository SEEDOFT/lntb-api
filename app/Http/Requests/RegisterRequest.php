<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'max:5', 'starts_with:+'],
            'phone_number' => ['required', 'string', 'regex:/^\d{7,14}$/', 'unique:users,phone_number'],
            'password' => ['required', 'confirmed', 'min:8', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'fcm_device_key' => ['nullable', 'required_with:fcm_token', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
