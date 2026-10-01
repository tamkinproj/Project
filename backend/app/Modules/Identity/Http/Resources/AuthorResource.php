<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
// Minimal public identity attached to posts, comments and notifications.
class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'username' => $this->username,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatarUrl(),
        ];
    }
}
