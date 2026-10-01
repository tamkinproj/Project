<?php

namespace App\Modules\Social\Http\Controllers;

use App\Modules\Identity\Http\Resources\AuthorResource;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Services\ProfileLookup;
use App\Modules\Social\Services\SocialGraph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RelationshipController
{
    public function __construct(
        private readonly SocialGraph $graph,
        private readonly ProfileLookup $lookup,
    ) {}

    public function follow(Request $request, string $username): JsonResponse
    {
        $target = $this->lookup->find($username, $request->user());
        $this->graph->follow($request->user(), $target);

        return response()->json(['following' => true]);
    }

    public function unfollow(Request $request, string $username): JsonResponse
    {
        $target = $this->lookup->find($username, $request->user(), ignoreBlocks: true);
        $this->graph->unfollow($request->user(), $target);

        return response()->json(['following' => false]);
    }

    public function block(Request $request, string $username): Response
    {
        $target = $this->lookup->find($username, $request->user(), ignoreBlocks: true);
        $this->graph->block($request->user(), $target);

        return response()->noContent();
    }

    public function unblock(Request $request, string $username): Response
    {
        $target = $this->lookup->find($username, $request->user(), ignoreBlocks: true);
        $this->graph->unblock($request->user(), $target);

        return response()->noContent();
    }

    public function blocked(Request $request): JsonResponse
    {
        $users = $request->user()->blockedUsers()->with('profile')->orderByDesc('blocks.created_at')->limit(200)->get();

        return response()->json([
            'data' => $users->map(fn (User $u) => (new AuthorResource($u->profile))->resolve($request)),
        ]);
    }
}
