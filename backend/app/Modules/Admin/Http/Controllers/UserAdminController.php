<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\RoleAssignment;
use App\Modules\Card\Models\Card;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Moderation\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// Administrators see account and status data needed for support and moderation.
// Private profile fields (legal name, phone, date of birth) are intentionally not exposed here.
class UserAdminController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
        ]);

        $term = isset($data['q']) ? str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(ltrim(trim($data['q']), '@'))) : null;

        $users = User::query()
            ->with('profile')
            ->when($term, fn ($q) => $q->where(fn ($q) => $q
                ->where('email', 'like', "%{$term}%")
                ->orWhereHas('profile', fn ($p) => $p->where('username', 'like', "{$term}%")->orWhere('display_name', 'ilike', "%{$term}%"))))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $u) => $this->summary($u)),
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'total' => $users->total()],
        ]);
    }

    public function show(string $user): JsonResponse
    {
        $model = User::with(['profile', 'roles'])->findOrFail($user);

        return response()->json(['data' => [
            ...$this->summary($model),
            'last_login_at' => $model->last_login_at?->toIso8601String(),
            'status_changed_at' => $model->status_changed_at?->toIso8601String(),
            'roles' => $model->roles->map(fn (Role $r) => ['slug' => $r->slug, 'name' => $r->name])->values(),
            'counts' => [
                'posts' => $model->posts()->count(),
                'followers' => DB::table('follows')->where('followee_id', $model->id)->count(),
                'reports_against' => DB::table('reports')->where('reportable_type', 'user')->where('reportable_id', $model->id)->count(),
                'active_sessions' => $model->tokens()->where('expires_at', '>', now())->count(),
            ],
            'cards' => $model->cards()->orderByDesc('created_at')->get()->map(fn (Card $c) => [
                'id' => $c->id, 'type' => $c->type->value, 'status' => $c->status->value, 'last4' => $c->number_last4,
            ]),
        ]]);
    }

    public function suspend(Request $request, string $user, ModerationService $moderation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $model = User::findOrFail($user);
        $moderation->suspendUser($request->user(), $model, $data['reason']);

        return response()->json(['status' => 'suspended']);
    }

    public function unsuspend(Request $request, string $user, ModerationService $moderation): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $model = User::findOrFail($user);
        $moderation->unsuspendUser($request->user(), $model, $data['reason'] ?? null);

        return response()->json(['status' => 'active']);
    }

    public function grantRole(Request $request, string $user, RoleAssignment $roles): JsonResponse
    {
        $data = $request->validate(['role' => ['required', 'string', 'exists:roles,slug']]);
        $roles->grant($request->user(), User::findOrFail($user), Role::where('slug', $data['role'])->firstOrFail());

        return response()->json(['granted' => $data['role']]);
    }

    public function revokeRole(Request $request, string $user, string $role, RoleAssignment $roles): JsonResponse
    {
        $roles->revoke($request->user(), User::findOrFail($user), Role::where('slug', $role)->firstOrFail());

        return response()->json(['revoked' => $role]);
    }

    private function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'username' => $user->profile?->username,
            'display_name' => $user->profile?->display_name,
            'status' => $user->status->value,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
