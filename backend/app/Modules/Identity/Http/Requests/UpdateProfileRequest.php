<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => UsernameRules::normalize($this->username)]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['sometimes', ...UsernameRules::rules($this->user()->id)],
            'display_name' => ['sometimes', 'required', 'string', 'min:1', 'max:50'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
            'location' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return UsernameRules::messages();
    }
}
