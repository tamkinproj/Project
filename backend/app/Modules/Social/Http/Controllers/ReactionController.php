<?php

namespace App\Modules\Social\Http\Controllers;

use App\Modules\Social\Enums\ReactionType;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Services\InteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReactionController
{
    public function __construct(private readonly InteractionService $interactions) {}

    public function update(Request $request, string $post): JsonResponse
    {
        $data = $request->validate(['type' => ['sometimes', Rule::enum(ReactionType::class)]]);
        $model = Post::visibleTo($request->user())->findOrFail($post);

        $type = ReactionType::from($data['type'] ?? ReactionType::Like->value);
        $this->interactions->react($request->user(), $model, $type);

        return response()->json(['reaction' => $type->value, 'reactions_count' => $model->fresh()->reactions_count]);
    }

    public function destroy(Request $request, string $post): JsonResponse
    {
        $model = Post::visibleTo($request->user())->findOrFail($post);
        $this->interactions->unreact($request->user(), $model);

        return response()->json(['reaction' => null, 'reactions_count' => $model->fresh()->reactions_count]);
    }
}
