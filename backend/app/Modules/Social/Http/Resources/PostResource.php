<?php

namespace App\Modules\Social\Http\Resources;

use App\Modules\Identity\Http\Resources\AuthorResource;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAuthor = $this->user_id === $request->user()->id;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'visibility' => $this->visibility->value,
            'author' => new AuthorResource($this->author->profile),
            'media' => $this->media->map(fn (PostMedia $m) => [
                'id' => $m->id,
                'url' => $m->url(),
                'width' => $m->width,
                'height' => $m->height,
            ])->values(),
            'counts' => [
                'comments' => $this->comments_count,
                'reactions' => $this->reactions_count,
            ],
            'viewer' => [
                'reaction' => $this->getAttributes()['viewer_reaction'] ?? null,
                'can_edit' => $isAuthor,
                'can_delete' => $isAuthor,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
        ];
    }
}
