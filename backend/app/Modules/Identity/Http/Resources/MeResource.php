<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Admin\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
// The signed-in user's own account. Only ever returned to that user.
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'profile' => [
                'username' => $profile->username,
                'display_name' => $profile->display_name,
                'bio' => $profile->bio,
                'location' => $profile->location,
                'avatar_url' => $profile->avatarUrl(),
            ],
            'privacy' => new PrivacyResource($this->privacy),
            'permissions' => array_map(fn (Permission $p) => $p->value, $this->permissions()),
        ];
    }
}
