<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => IdentityNormalizer::phone($this->input('phone_number')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'max:5', 'starts_with:+'],
            'phone_number' => ['required', 'string', 'regex:/^\d{7,14}$/'],
            'password' => ['required', 'confirmed', 'max:255', Password::min(12)->mixedCase()->numbers()->symbols()],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
