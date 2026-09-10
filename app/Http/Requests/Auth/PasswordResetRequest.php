<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:180'],
            'password' => ['required', 'confirmed', Password::defaults()->min(8)->letters()->numbers()],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'e-mail', 'password' => 'senha'];
    }
}
