<?php

namespace App\Modules\Social\Http\Requests;

use App\Modules\Social\Enums\PostVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'visibility' => ['sometimes', Rule::enum(PostVisibility::class)],
        ];
    }
}
