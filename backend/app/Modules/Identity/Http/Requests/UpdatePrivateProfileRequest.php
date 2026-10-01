<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter the phone number in international format, e.g. +639171234567.'];
    }
}
