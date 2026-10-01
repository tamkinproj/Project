<?php

namespace App\Modules\Identity\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Card\Services\CardService;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Models\PostMedia;
use App\Modules\Social\Services\PostCounters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Permanent deletion: personal data, content and sessions are removed; cards are revoked.
class DeleteAccount
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CardService $cards,
        private readonly PostCounters $counters,
    ) {}

    public function handle(User $user): void
    {
        $this->audit->record('account.deleted', actor: $user, subject: $user);

        DB::transaction(function () use ($user) {
            $mediaPaths = PostMedia::whereIn('post_id', $user->posts()->select('id'))->pluck('path')
                ->push($user->profile?->avatar_path)
                ->filter()
                ->values()
                ->all();

            $affectedPostIds = DB::table('comments')->where('user_id', $user->id)->pluck('post_id')
                ->merge(DB::table('reactions')->where('user_id', $user->id)->pluck('post_id'))
                ->unique()
                ->all();

            $this->cards->revokeAllFor($user, 'account_deleted');
            $user->tokens()->delete();
            $user->delete();

            $this->counters->refresh($affectedPostIds);

            DB::afterCommit(fn () => Storage::disk(config('ecosystem.media.disk'))->delete($mediaPaths));
        });
    }
}
