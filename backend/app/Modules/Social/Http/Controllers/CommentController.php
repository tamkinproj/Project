<?php

namespace App\Modules\Social\Http\Controllers;

use App\Modules\Social\Http\Requests\StoreCommentRequest;
use App\Modules\Social\Http\Resources\CommentResource;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Services\InteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController
{
    public function __construct(private readonly InteractionService $interactions) {}

    public function index(Request $request, string $post): JsonResponse
    {
        $viewer = $request->user();
        $model = Post::visibleTo($viewer)->findOrFail($post);

        $comments = $model->comments()
            ->visibleTo($viewer)
            ->with('author.profile')
            ->orderBy('comments.id')
            ->cursorPaginate(30);

        return response()->json([
            'data' => $comments->getCollection()->map(fn (Comment $c) => (new CommentResource($c, $model->user_id))->resolve($request)),
            'meta' => ['next_cursor' => $comments->nextCursor()?->encode()],
        ]);
    }

    public function store(StoreCommentRequest $request, string $post): CommentResource
    {
        $model = Post::visibleTo($request->user())->findOrFail($post);
        $comment = $this->interactions->comment($request->user(), $model, $request->validated('body'));

        return new CommentResource($comment->load('author.profile'), $model->user_id);
    }

    public function destroy(Request $request, string $comment): Response
    {
        $model = Comment::with('post')->findOrFail($comment);
        abort_unless(Gate::allows('delete', $model), 404);

        $this->interactions->deleteComment($model);

        return response()->noContent();
    }
}
