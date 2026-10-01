<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Re-authentication for destructive account actions.
class ConfirmPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = ['password' => ['required', 'string', 'current_password:sanctum']];

        if ($this->isMethod('DELETE')) {
            $rules['confirmation'] = ['required', 'string', 'in:DELETE'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Type DELETE to confirm.'];
    }
}
