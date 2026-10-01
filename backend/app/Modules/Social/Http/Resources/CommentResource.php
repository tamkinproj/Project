<?php

namespace App\Modules\Social\Http\Resources;

use App\Modules\Identity\Http\Resources\AuthorResource;
use App\Modules\Social\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function __construct(Comment $resource, private readonly ?string $postAuthorId = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $viewerId = $request->user()->id;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => new AuthorResource($this->author->profile),
            'created_at' => $this->created_at->toIso8601String(),
            'viewer' => [
                'can_delete' => $this->user_id === $viewerId || $this->postAuthorId === $viewerId,
            ],
        ];
    }
}
