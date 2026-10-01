<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 *
 * What any other user may see. Never includes the user ID, email, phone,
 * legal name, date of birth, card or security information.
 */
class PublicProfileResource extends JsonResource
{
    /** @param array{is_self: bool, is_following: bool, follows_you: bool, has_blocked: bool, can_follow: bool}|null $relationship */
    public function __construct(Profile $resource, private readonly ?array $relationship = null, private readonly ?array $counts = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $privacy = $this->user->privacy;

        return [
            'username' => $this->username,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'location' => $privacy?->show_location ? $this->location : null,
            'avatar_url' => $this->avatarUrl(),
            'joined_at' => $this->created_at->format('Y-m'),
            'counts' => $this->when($this->counts !== null, fn () => $this->counts),
            'relationship' => $this->when($this->relationship !== null, fn () => $this->relationship),
        ];
    }
}
