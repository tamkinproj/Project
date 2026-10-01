<?php

namespace App\Modules\Social\Models;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Enums\ModerationStatus;
use App\Modules\Social\Enums\PostVisibility;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[UseFactory(PostFactory::class)]
class Post extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['body', 'visibility', 'moderation_status', 'edited_at'];

    protected $attributes = [
        'visibility' => 'public',
        'moderation_status' => 'visible',
        'comments_count' => 0,
        'reactions_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'visibility' => PostVisibility::class,
            'moderation_status' => ModerationStatus::class,
            'comments_count' => 'integer',
            'reactions_count' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Adds the viewer's own reaction (if any) without an extra query per post.
    public function scopeWithViewerState(Builder $query, User $viewer): Builder
    {
        return $query->select('posts.*')->addSelect([
            'viewer_reaction' => DB::table('reactions')
                ->select('type')
                ->whereColumn('reactions.post_id', 'posts.id')
                ->where('reactions.user_id', $viewer->id)
                ->limit(1),
        ]);
    }

    /**
     * The single source of truth for "may this viewer see this post". Used by the feed,
     * profile timelines and single-post lookups, so every read path enforces the same rules:
     * moderation status, author account status, blocks in both directions, and visibility.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $query
            ->where('posts.moderation_status', ModerationStatus::Visible)
            ->whereHas('author', fn (Builder $q) => $q->where('status', AccountStatus::Active))
            ->whereNotExists(fn ($q) => $q->from('blocks')
                ->where(fn ($q) => $q->whereColumn('blocks.blocker_id', 'posts.user_id')->where('blocks.blocked_id', $viewer->id))
                ->orWhere(fn ($q) => $q->where('blocks.blocker_id', $viewer->id)->whereColumn('blocks.blocked_id', 'posts.user_id')))
            ->where(fn (Builder $q) => $q
                ->where('posts.user_id', $viewer->id)
                ->orWhere('posts.visibility', PostVisibility::Public)
                ->orWhere(fn (Builder $q) => $q
                    ->where('posts.visibility', PostVisibility::Followers)
                    ->whereExists(fn ($q) => $q->from('follows')
                        ->whereColumn('follows.followee_id', 'posts.user_id')
                        ->where('follows.follower_id', $viewer->id))));
    }
}
