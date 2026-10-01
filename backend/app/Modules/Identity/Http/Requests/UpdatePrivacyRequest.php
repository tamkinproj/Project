<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\FollowPolicy;
use App\Modules\Identity\Enums\MessagePolicy;
use App\Modules\Social\Enums\PostVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrivacyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'profile_searchable' => ['sometimes', 'boolean'],
            'show_location' => ['sometimes', 'boolean'],
            'who_can_follow' => ['sometimes', Rule::enum(FollowPolicy::class)],
            'who_can_message' => ['sometimes', Rule::enum(MessagePolicy::class)],
            'default_post_visibility' => ['sometimes', Rule::enum(PostVisibility::class)],
        ];
    }
}
