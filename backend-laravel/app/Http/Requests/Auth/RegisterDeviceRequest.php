<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'public_key' => ['required', 'string', 'max:1024'],
            'android_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/'],
            'model' => ['nullable', 'string', 'max:80'],
            'manufacturer' => ['nullable', 'string', 'max:80'],
            'os_version' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'integrity_token' => ['nullable', 'string', 'max:8192'],
        ];
    }
}
