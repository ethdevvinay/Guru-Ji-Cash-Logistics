<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\AppClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:128'],
            'app' => ['required', Rule::enum(AppClient::class)],
        ];
    }
}
