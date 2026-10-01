<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\Role;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoleAssignment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function grant(?User $actor, User $target, Role $role): void
    {
        $this->guard($actor, $target, $role);

        $inserted = DB::table('role_user')->insertOrIgnore([
            'role_id' => $role->id,
            'user_id' => $target->id,
            'assigned_by' => $actor?->id,
            'created_at' => now(),
        ]);

        if ($inserted > 0) {
            $target->forgetPermissions();
            $this->audit->record('admin.role_granted', actor: $actor, subject: $target, metadata: ['role' => $role->slug]);
        }
    }

    public function revoke(User $actor, User $target, Role $role): void
    {
        $this->guard($actor, $target, $role);

        if ($role->slug === Role::SUPER_ADMIN && DB::table('role_user')->where('role_id', $role->id)->count() <= 1) {
            throw new HttpException(422, 'The last super administrator cannot be removed.');
        }

        $deleted = DB::table('role_user')->where(['role_id' => $role->id, 'user_id' => $target->id])->delete();

        if ($deleted > 0) {
            $target->forgetPermissions();
            $this->audit->record('admin.role_revoked', actor: $actor, subject: $target, metadata: ['role' => $role->slug]);
        }
    }

    // $actor is null only when run from the server console by an operator.
    private function guard(?User $actor, User $target, Role $role): void
    {
        if (! $actor) {
            return;
        }

        if ($actor->is($target)) {
            throw new HttpException(403, 'You cannot change your own roles.');
        }

        $superAdmin = Role::where('slug', Role::SUPER_ADMIN)->first();
        $actorIsSuper = $superAdmin && DB::table('role_user')->where(['role_id' => $superAdmin->id, 'user_id' => $actor->id])->exists();

        if ($role->slug === Role::SUPER_ADMIN && ! $actorIsSuper) {
            throw new HttpException(403, 'Only a super administrator can grant or remove that role.');
        }
    }
}
