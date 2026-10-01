<?php

namespace App\Modules\Social\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Social\Models\Post;

// Viewing is enforced by Post::scopeVisibleTo(); these cover ownership of changes.
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
