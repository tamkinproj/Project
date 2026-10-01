<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
            'username' => UsernameRules::normalize($this->username),
            'display_name' => is_string($this->display_name) ? trim($this->display_name) : $this->display_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'username' => UsernameRules::rules(),
            'display_name' => ['required', 'string', 'min:1', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return UsernameRules::messages();
    }
}
