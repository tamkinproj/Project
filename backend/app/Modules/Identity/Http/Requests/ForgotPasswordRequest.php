<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email]);
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254']];
    }
}
