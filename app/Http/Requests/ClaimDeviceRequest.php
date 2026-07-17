<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MacAddress;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class ClaimDeviceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('mac_address'))) {
            try {
                $this->merge(['mac_address' => MacAddress::normalize($this->input('mac_address'))]);
            } catch (InvalidArgumentException) {
                // Validation below returns the public field error.
            }
        }
    }

    public function rules(): array
    {
        return [
            'mac_address' => ['required', 'regex:/^(?:[0-9A-F]{2}:){5}[0-9A-F]{2}$/'],
            'claim_code' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
