<?php

namespace App\Modules\Admin\Models;

use App\Modules\Admin\Enums\Permission;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    use HasUlids;

    public const SUPER_ADMIN = 'super_admin';

    protected $fillable = ['slug', 'name', 'description'];

    /** @return list<Permission> */
    public function permissions(): array
    {
        if ($this->slug === self::SUPER_ADMIN) {
            return Permission::cases();
        }

        return DB::table('role_permissions')
            ->where('role_id', $this->getKey())
            ->pluck('permission')
            ->map(fn (string $p) => Permission::tryFrom($p))
            ->filter()
            ->values()
            ->all();
    }

    /** @param  list<Permission>  $permissions */
    public function syncPermissions(array $permissions): void
    {
        DB::transaction(function () use ($permissions) {
            DB::table('role_permissions')->where('role_id', $this->getKey())->delete();
            DB::table('role_permissions')->insert(array_map(
                fn (Permission $p) => ['role_id' => $this->getKey(), 'permission' => $p->value],
                $permissions,
            ));
        });
    }
}
