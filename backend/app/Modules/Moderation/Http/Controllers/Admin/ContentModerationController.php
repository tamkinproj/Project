<?php

namespace App\Modules\Moderation\Http\Controllers\Admin;

use App\Modules\Moderation\Models\ModerationAction;
use App\Modules\Moderation\Services\ModerationService;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentModerationController
{
    public function __construct(private readonly ModerationService $moderation) {}

    public function hidePost(Request $request, string $post): JsonResponse
    {
        $this->moderation->hidePost($request->user(), Post::findOrFail($post), $this->reason($request));

        return response()->json(['moderation_status' => 'hidden']);
    }

    public function restorePost(Request $request, string $post): JsonResponse
    {
        $this->moderation->restorePost($request->user(), Post::findOrFail($post), $this->reason($request));

        return response()->json(['moderation_status' => 'visible']);
    }

    public function hideComment(Request $request, string $comment): JsonResponse
    {
        $this->moderation->hideComment($request->user(), Comment::findOrFail($comment), $this->reason($request));

        return response()->json(['moderation_status' => 'hidden']);
    }

    public function restoreComment(Request $request, string $comment): JsonResponse
    {
        $this->moderation->restoreComment($request->user(), Comment::findOrFail($comment), $this->reason($request));

        return response()->json(['moderation_status' => 'visible']);
    }

    public function history(Request $request): JsonResponse
    {
        $actions = ModerationAction::with('moderator.profile')
            ->when($request->query('target_id'), fn ($q, $id) => $q->where('target_id', $id))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data' => $actions->getCollection()->map(fn (ModerationAction $a) => [
                'id' => $a->id,
                'action' => $a->action->value,
                'target_type' => $a->target_type,
                'target_id' => $a->target_id,
                'report_id' => $a->report_id,
                'reason' => $a->reason,
                'moderator' => $a->moderator?->profile?->username,
                'created_at' => $a->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $actions->currentPage(), 'last_page' => $actions->lastPage(), 'total' => $actions->total()],
        ]);
    }

    private function reason(Request $request): ?string
    {
        return $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;
    }
}
