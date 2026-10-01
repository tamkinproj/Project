<?php

namespace App\Modules\Social\Http\Controllers;

use App\Modules\Social\Enums\PostVisibility;
use App\Modules\Social\Http\Requests\StorePostRequest;
use App\Modules\Social\Http\Requests\UpdatePostRequest;
use App\Modules\Social\Http\Resources\PostResource;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostController
{
    public function __construct(private readonly PostService $posts) {}

    public function store(StorePostRequest $request): JsonResponse
    {
        $user = $request->user();
        $visibility = $request->enum('visibility', PostVisibility::class) ?? $user->privacy->default_post_visibility;

        $post = $this->posts->create($user, $request->validated('body'), $visibility, $request->file('images', []));

        return (new PostResource($this->load($post->id, $request)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $post): PostResource
    {
        return new PostResource($this->load($post, $request));
    }

    public function update(UpdatePostRequest $request, string $post): PostResource
    {
        $model = $request->user()->posts()->findOrFail($post);
        Gate::authorize('update', $model);

        $changes = $request->validated();

        if (array_key_exists('body', $changes) && PostService::cleanBody($changes['body']) === null && ! $model->media()->exists()) {
            throw ValidationException::withMessages(['body' => 'A post without photos needs some text.']);
        }

        $this->posts->update($model, $changes);

        return new PostResource($this->load($model->id, $request));
    }

    public function destroy(Request $request, string $post): Response
    {
        $model = $request->user()->posts()->findOrFail($post);
        Gate::authorize('delete', $model);

        $this->posts->delete($model);

        return response()->noContent();
    }

    private function load(string $id, Request $request): Post
    {
        $viewer = $request->user();

        return Post::query()
            ->visibleTo($viewer)
            ->withViewerState($viewer)
            ->with(['author.profile', 'media'])
            ->findOrFail($id);
    }
}
