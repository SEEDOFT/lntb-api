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
            'email' => IdentityNormalizer::email($this->input('email')),
            'phone_number' => IdentityNormalizer::phone($this->input('phone_number')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone_number' => ['nullable', 'required_without:email', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone_number'],
            'email' => ['nullable', 'required_without:phone_number', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'max:255', Password::min(12)->mixedCase()->numbers()->symbols()],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
