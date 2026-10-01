<?php

namespace App\Modules\Social\Models;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasUlids;

    protected $fillable = ['body', 'moderation_status'];

    protected $attributes = ['moderation_status' => 'visible'];

    protected function casts(): array
    {
        return ['moderation_status' => ModerationStatus::class];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Visibility of the parent post is checked separately by the caller.
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $query
            ->where('comments.moderation_status', ModerationStatus::Visible)
            ->whereHas('author', fn (Builder $q) => $q->where('status', AccountStatus::Active))
            ->whereNotExists(fn ($q) => $q->from('blocks')
                ->where(fn ($q) => $q->whereColumn('blocks.blocker_id', 'comments.user_id')->where('blocks.blocked_id', $viewer->id))
                ->orWhere(fn ($q) => $q->where('blocks.blocker_id', $viewer->id)->whereColumn('blocks.blocked_id', 'comments.user_id')));
    }
}
