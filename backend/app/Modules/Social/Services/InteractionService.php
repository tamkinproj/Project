<?php

namespace App\Modules\Social\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\SocialActivity;
use App\Modules\Social\Enums\ModerationStatus;
use App\Modules\Social\Enums\ReactionType;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use Illuminate\Support\Facades\DB;

class InteractionService
{
    public function react(User $user, Post $post, ReactionType $type): void
    {
        $created = DB::transaction(function () use ($user, $post, $type) {
            $inserted = DB::table('reactions')->insertOrIgnore([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'type' => $type->value,
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                DB::table('reactions')->where(['post_id' => $post->id, 'user_id' => $user->id])->update(['type' => $type->value]);

                return false;
            }

            $post->increment('reactions_count');

            return true;
        });

        if ($created && $post->user_id !== $user->id) {
            $post->author->notify(new SocialActivity(SocialActivity::REACTED, $user->id, $post->id));
        }
    }

    public function unreact(User $user, Post $post): void
    {
        DB::transaction(function () use ($user, $post) {
            $deleted = DB::table('reactions')->where(['post_id' => $post->id, 'user_id' => $user->id])->delete();

            if ($deleted > 0) {
                Post::whereKey($post->id)->where('reactions_count', '>', 0)->decrement('reactions_count');
            }
        });
    }

    public function comment(User $user, Post $post, string $body): Comment
    {
        $comment = DB::transaction(function () use ($user, $post, $body) {
            $comment = $post->comments()->make(['body' => PostService::cleanBody($body)]);
            $comment->author()->associate($user);
            $comment->save();

            $post->increment('comments_count');

            return $comment;
        });

        if ($post->user_id !== $user->id) {
            $post->author->notify(new SocialActivity(SocialActivity::COMMENTED, $user->id, $post->id));
        }

        return $comment;
    }

    public function deleteComment(Comment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $wasVisible = $comment->moderation_status === ModerationStatus::Visible;
            $comment->delete();

            if ($wasVisible) {
                Post::whereKey($comment->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
            }
        });
    }
}
