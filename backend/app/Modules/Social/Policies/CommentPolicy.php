<?php

namespace App\Modules\Social\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Social\Models\Comment;

class CommentPolicy
{
    // The commenter, or the author of the post it was left on.
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $comment->post->user_id === $user->id;
    }
}
