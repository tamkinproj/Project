<?php

namespace App\Modules\Moderation\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Models\Report;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Report */
class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'details' => $this->details,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'reporter' => self::person($this->reporter),
            'reviewer' => self::person($this->reviewer),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'resolution_note' => $this->resolution_note,
            'target' => $this->target(),
        ];
    }

    private function target(): array
    {
        $target = $this->reportable;
        $base = ['type' => $this->reportable_type, 'id' => $this->reportable_id, 'exists' => (bool) $target];

        return match (true) {
            $target instanceof Post => [...$base,
                'author' => self::person($target->author),
                'body' => $target->body,
                'media' => $target->media->map(fn (PostMedia $m) => ['url' => $m->url(), 'width' => $m->width, 'height' => $m->height])->values(),
                'visibility' => $target->visibility->value,
                'moderation_status' => $target->moderation_status->value,
            ],
            $target instanceof Comment => [...$base,
                'author' => self::person($target->author),
                'body' => $target->body,
                'post_id' => $target->post_id,
                'moderation_status' => $target->moderation_status->value,
            ],
            $target instanceof User => [...$base,
                'author' => self::person($target),
                'body' => $target->profile?->bio,
                'account_status' => $target->status->value,
            ],
            default => $base,
        };
    }

    public static function person(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->profile?->username,
            'display_name' => $user->profile?->display_name ?? Str::limit($user->id, 8),
            'status' => $user->status->value,
        ];
    }
}
