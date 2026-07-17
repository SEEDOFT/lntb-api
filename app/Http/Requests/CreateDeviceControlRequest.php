<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateDeviceControlRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'control_type' => ['required', 'string', Rule::in(config('device_controls.allowed', []))],
            'control_data' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== [] && array_is_list($value)) {
                    $fail('The control data must be a JSON object.');
                }
                if (strlen((string) json_encode($value)) > 8192) {
                    $fail('The control data may not exceed 8 KB.');
                }
            }],
        ];
    }
}
