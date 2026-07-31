<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_ref' => ['required', 'string', 'max:64'],
            'activation_token' => ['required', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
