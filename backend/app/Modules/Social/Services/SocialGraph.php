<?php

namespace App\Modules\Social\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Enums\FollowPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\SocialActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SocialGraph
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function follow(User $follower, User $target): void
    {
        if ($follower->is($target)) {
            throw ValidationException::withMessages(['username' => 'You cannot follow yourself.']);
        }

        if ($follower->isBlockedWith($target)) {
            throw new HttpException(404, 'Not found.');
        }

        if ($target->privacy?->who_can_follow === FollowPolicy::Nobody) {
            throw new HttpException(403, 'This person is not accepting new followers.');
        }

        $inserted = DB::table('follows')->insertOrIgnore([
            'follower_id' => $follower->id,
            'followee_id' => $target->id,
            'created_at' => now(),
        ]);

        if ($inserted > 0) {
            $target->notify(new SocialActivity(SocialActivity::FOLLOWED, $follower->id));
        }
    }

    public function unfollow(User $follower, User $target): void
    {
        DB::table('follows')->where(['follower_id' => $follower->id, 'followee_id' => $target->id])->delete();
    }

    // Blocking also severs follow relationships in both directions.
    public function block(User $blocker, User $target): void
    {
        if ($blocker->is($target)) {
            throw ValidationException::withMessages(['username' => 'You cannot block yourself.']);
        }

        DB::transaction(function () use ($blocker, $target) {
            DB::table('blocks')->insertOrIgnore([
                'blocker_id' => $blocker->id,
                'blocked_id' => $target->id,
                'created_at' => now(),
            ]);

            DB::table('follows')
                ->where(fn ($q) => $q->where('follower_id', $blocker->id)->where('followee_id', $target->id))
                ->orWhere(fn ($q) => $q->where('follower_id', $target->id)->where('followee_id', $blocker->id))
                ->delete();
        });

        $this->audit->record('social.user_blocked', actor: $blocker, subject: $target);
    }

    public function unblock(User $blocker, User $target): void
    {
        $deleted = DB::table('blocks')->where(['blocker_id' => $blocker->id, 'blocked_id' => $target->id])->delete();

        if ($deleted > 0) {
            $this->audit->record('social.user_unblocked', actor: $blocker, subject: $target);
        }
    }
}
