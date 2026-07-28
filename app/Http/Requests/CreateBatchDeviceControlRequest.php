<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateBatchDeviceControlRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1', 'max:20'],
            'device_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
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
