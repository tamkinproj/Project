<?php

namespace App\Modules\Social\Services;

use Illuminate\Support\Facades\DB;

// Denormalized counters keep feeds cheap; this recalculates them when bulk changes happen.
class PostCounters
{
    /** @param array<int, string> $postIds */
    public function refresh(array $postIds): void
    {
        if ($postIds === []) {
            return;
        }

        foreach (array_chunk($postIds, 500) as $chunk) {
            DB::table('posts')->whereIn('id', $chunk)->update([
                'comments_count' => DB::raw("(SELECT count(*) FROM comments WHERE comments.post_id = posts.id AND comments.moderation_status = 'visible')"),
                'reactions_count' => DB::raw('(SELECT count(*) FROM reactions WHERE reactions.post_id = posts.id)'),
            ]);
        }
    }
}
