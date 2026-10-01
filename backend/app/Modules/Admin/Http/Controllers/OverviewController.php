<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OverviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $users = DB::table('users')->selectRaw("count(*) as total, count(*) filter (where status = 'active') as active, count(*) filter (where status = 'suspended') as suspended, count(*) filter (where created_at > now() - interval '7 days') as new_7d")->first();
        $cards = DB::table('cards')->where('type', 'physical')->selectRaw("count(*) filter (where status = 'active') as active, count(*) filter (where status = 'pending_activation') as pending, count(*) filter (where replacement_requested_at is not null and status not in ('replaced', 'revoked')) as replacement_requests")->first();

        return response()->json([
            'users' => $user->hasPermission(Permission::UsersView) ? (array) $users : null,
            'reports' => $user->hasPermission(Permission::ReportsReview) ? ['open' => DB::table('reports')->where('status', 'open')->count()] : null,
            'content' => [
                'posts_24h' => DB::table('posts')->where('created_at', '>', now()->subDay())->count(),
                'comments_24h' => DB::table('comments')->where('created_at', '>', now()->subDay())->count(),
            ],
            'cards' => $user->hasPermission(Permission::CardsManage) ? (array) $cards : null,
            'permissions' => array_map(fn (Permission $p) => $p->value, $user->permissions()),
        ]);
    }
}
