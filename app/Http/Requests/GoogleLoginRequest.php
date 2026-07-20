<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GoogleLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }
}
