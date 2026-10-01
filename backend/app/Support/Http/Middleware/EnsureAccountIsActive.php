<?php

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Defense in depth: suspension revokes tokens, but a token must never work for a non-active account.
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'This account is not active.',
                'code' => 'account_inactive',
            ], 403);
        }

        return $next($request);
    }
}
