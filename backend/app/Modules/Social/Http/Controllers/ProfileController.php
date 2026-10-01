<?php

namespace App\Modules\Social\Http\Controllers;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Enums\FollowPolicy;
use App\Modules\Identity\Http\Resources\AuthorResource;
use App\Modules\Identity\Http\Resources\PublicProfileResource;
use App\Modules\Identity\Models\Profile;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Http\Resources\PostResource;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Services\ProfileLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController
{
    public function __construct(private readonly ProfileLookup $lookup) {}

    public function show(Request $request, string $username): PublicProfileResource
    {
        $viewer = $request->user();
        $user = $this->lookup->find($username, $viewer);
        $isSelf = $user->is($viewer);
        $hasBlocked = ! $isSelf && $viewer->hasBlocked($user);

        $relationship = [
            'is_self' => $isSelf,
            'is_following' => ! $isSelf && $viewer->isFollowing($user),
            'follows_you' => ! $isSelf && $user->isFollowing($viewer),
            'has_blocked' => $hasBlocked,
            'can_follow' => ! $isSelf && ! $hasBlocked && $user->privacy->who_can_follow === FollowPolicy::Everyone,
        ];

        $counts = [
            'followers' => $this->activeUsers($user->followers()->getQuery())->count(),
            'following' => $this->activeUsers($user->following()->getQuery())->count(),
            'posts' => Post::visibleTo($viewer)->where('posts.user_id', $user->id)->count(),
        ];

        return new PublicProfileResource($user->profile, $relationship, $counts);
    }

    public function posts(Request $request, string $username): AnonymousResourceCollection
    {
        $viewer = $request->user();
        $user = $this->lookup->find($username, $viewer);

        $posts = Post::query()
            ->visibleTo($viewer)
            ->withViewerState($viewer)
            ->where('posts.user_id', $user->id)
            ->with(['author.profile', 'media'])
            ->orderByDesc('posts.id')
            ->cursorPaginate(20);

        return PostResource::collection($posts);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:1', 'max:50']]);
        $viewer = $request->user();
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(ltrim(trim($data['q']), '@')));

        $profiles = Profile::query()
            ->where(fn ($q) => $q->where('username', 'like', $term.'%')->orWhere('display_name', 'ilike', '%'.$term.'%'))
            ->whereHas('user', fn (Builder $q) => $q->where('status', AccountStatus::Active))
            ->where(fn ($q) => $q
                ->where('profiles.user_id', $viewer->id)
                ->orWhereExists(fn ($q) => $q->from('privacy_settings')
                    ->whereColumn('privacy_settings.user_id', 'profiles.user_id')
                    ->where('privacy_settings.profile_searchable', true)))
            ->whereNotExists(fn ($q) => $q->from('blocks')
                ->where(fn ($q) => $q->whereColumn('blocks.blocker_id', 'profiles.user_id')->where('blocks.blocked_id', $viewer->id))
                ->orWhere(fn ($q) => $q->where('blocks.blocker_id', $viewer->id)->whereColumn('blocks.blocked_id', 'profiles.user_id')))
            ->orderByRaw('CASE WHEN username = ? THEN 0 WHEN username LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])
            ->orderBy('username')
            ->limit(20)
            ->get();

        return AuthorResource::collection($profiles);
    }

    public function followers(Request $request, string $username): JsonResponse
    {
        $user = $this->lookup->find($username, $request->user());

        return $this->profileList($request, $user->followers()->getQuery());
    }

    public function following(Request $request, string $username): JsonResponse
    {
        $user = $this->lookup->find($username, $request->user());

        return $this->profileList($request, $user->following()->getQuery());
    }

    private function profileList(Request $request, Builder $users): JsonResponse
    {
        $viewer = $request->user();

        $page = $this->activeUsers($users)
            ->whereNotExists(fn ($q) => $q->from('blocks')
                ->where(fn ($q) => $q->whereColumn('blocks.blocker_id', 'users.id')->where('blocks.blocked_id', $viewer->id))
                ->orWhere(fn ($q) => $q->where('blocks.blocker_id', $viewer->id)->whereColumn('blocks.blocked_id', 'users.id')))
            ->with('profile')
            ->orderByDesc('followed_at')
            ->orderByDesc('users.id')
            ->cursorPaginate(30, ['users.*', 'follows.created_at as followed_at']);

        return response()->json([
            'data' => $page->getCollection()->map(fn (User $u) => (new AuthorResource($u->profile))->resolve($request)),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    private function activeUsers(Builder $users): Builder
    {
        return $users->where('users.status', AccountStatus::Active);
    }
}
