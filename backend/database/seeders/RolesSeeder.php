<?php

namespace Database\Seeders;

use App\Modules\Admin\Enums\Permission;
use App\Modules\Admin\Models\Role;
use Illuminate\Database\Seeder;

// Idempotent: safe to run on every deployment.
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::SUPER_ADMIN => ['Super administrator', 'Every permission, including role management.', []],
            'moderator' => ['Moderator', 'Reviews reports and moderates content and accounts.', [
                Permission::UsersView, Permission::UsersSuspend, Permission::ReportsReview, Permission::ContentModerate,
            ]],
            'card_officer' => ['Card officer', 'Issues, tracks and revokes physical member cards.', [
                Permission::UsersView, Permission::CardsIssue, Permission::CardsManage,
            ]],
            'auditor' => ['Auditor', 'Read-only access to audit logs and system health.', [
                Permission::UsersView, Permission::AuditView, Permission::SystemHealth,
            ]],
        ];

        foreach ($roles as $slug => [$name, $description, $permissions]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);

            if ($slug !== Role::SUPER_ADMIN) {
                $role->syncPermissions($permissions);
            }
        }
    }
}
