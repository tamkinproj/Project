<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Http\Resources\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SessionController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = $request->user()->tokens()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return SessionResource::collection($sessions);
    }

    // Scoped to the caller's own tokens: another user's session ID is simply not found.
    public function destroy(Request $request, string $session): Response
    {
        $token = $request->user()->tokens()->whereKey($session)->firstOrFail();
        $token->delete();

        $this->audit->record('session.revoked', metadata: ['session_id' => $session]);

        return response()->noContent();
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $revoked = $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        $this->audit->record('session.revoked_others', metadata: ['count' => $revoked]);

        return response()->json(['revoked' => $revoked]);
    }
}
