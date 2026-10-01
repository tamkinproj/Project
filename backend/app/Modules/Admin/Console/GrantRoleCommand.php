<?php

namespace App\Modules\Admin\Console;

use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\RoleAssignment;
use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;

// Bootstraps the first administrator. The person registers normally first, so no
// password ever passes through the command line.
class GrantRoleCommand extends Command
{
    protected $signature = 'admin:grant {email : Email of an existing account} {role=super_admin : Role slug}';

    protected $description = 'Grant an administrative role to an existing account';

    public function handle(RoleAssignment $roles): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        $role = Role::where('slug', $this->argument('role'))->first();

        if (! $user) {
            $this->error('No account with that email. Register it first.');

            return self::FAILURE;
        }

        if (! $role) {
            $this->error('Unknown role. Available: '.Role::pluck('slug')->implode(', ').' (run `php artisan db:seed` to create the default roles).');

            return self::FAILURE;
        }

        $roles->grant(null, $user, $role);
        $this->info("Granted {$role->slug} to {$user->email}.");

        return self::SUCCESS;
    }
}
