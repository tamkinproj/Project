<?php

namespace App\Modules\Social\Http\Controllers;

use App\Modules\Social\Http\Resources\PostResource;
use App\Modules\Social\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

// Chronological feed. "following" = people you follow + you; "everyone" = all posts you may see.
class FeedController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $scope = $request->query('scope') === 'everyone' ? 'everyone' : 'following';

        $posts = Post::query()
            ->visibleTo($viewer)
            ->withViewerState($viewer)
            ->when($scope === 'following', fn ($q) => $q->where(fn ($q) => $q
                ->where('posts.user_id', $viewer->id)
                ->orWhereIn('posts.user_id', DB::table('follows')->select('followee_id')->where('follower_id', $viewer->id))))
            ->with(['author.profile', 'media'])
            ->orderByDesc('posts.id')
            ->cursorPaginate(20);

        return PostResource::collection($posts);
    }
}
