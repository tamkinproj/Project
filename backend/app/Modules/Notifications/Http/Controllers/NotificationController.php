<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Identity\Http\Resources\AuthorResource;
use App\Modules\Identity\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $category = $request->query('category');

        $page = $viewer->notifications()
            ->when(in_array($category, ['security', 'social'], true), fn ($q) => $q->where('data->category', $category))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(30);

        // Resolve actors at read time and drop anything from people who are gone or blocked.
        $actorIds = $page->getCollection()->pluck('data.actor_id')->filter()->unique()->values();
        $blocked = DB::table('blocks')
            ->where(fn ($q) => $q->where('blocker_id', $viewer->id)->whereIn('blocked_id', $actorIds))
            ->orWhere(fn ($q) => $q->where('blocked_id', $viewer->id)->whereIn('blocker_id', $actorIds))
            ->get()
            ->flatMap(fn ($b) => [$b->blocker_id, $b->blocked_id])
            ->all();
        $actors = Profile::whereIn('user_id', $actorIds)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get()
            ->keyBy('user_id');

        $items = $page->getCollection()
            ->map(function (DatabaseNotification $n) use ($actors, $blocked, $request) {
                $actorId = $n->data['actor_id'] ?? null;

                if ($actorId && (! $actors->has($actorId) || in_array($actorId, $blocked, true))) {
                    return null;
                }

                return [
                    'id' => $n->id,
                    'category' => $n->data['category'] ?? 'social',
                    'event' => $n->data['event'] ?? null,
                    'message' => $n->data['message'] ?? null,
                    'actor' => $actorId ? (new AuthorResource($actors[$actorId]))->resolve($request) : null,
                    'post_id' => $n->data['post_id'] ?? null,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at->toIso8601String(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => ['next_cursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'security_unread' => $user->unreadNotifications()->where('data->category', 'security')->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): Response
    {
        abort_unless(Str::isUuid($notification), 404);

        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->noContent();
    }

    public function markAllRead(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
