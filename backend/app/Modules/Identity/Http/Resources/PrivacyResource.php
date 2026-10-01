<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\PrivacySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrivacySetting */
class PrivacyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'profile_searchable' => $this->profile_searchable,
            'show_location' => $this->show_location,
            'who_can_follow' => $this->who_can_follow->value,
            'who_can_message' => $this->who_can_message->value,
            'default_post_visibility' => $this->default_post_visibility->value,
        ];
    }
}
