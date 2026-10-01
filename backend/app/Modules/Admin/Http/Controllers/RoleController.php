<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Enums\Permission;
use App\Modules\Admin\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Role::orderBy('name')->get()->map(fn (Role $r) => [
                'slug' => $r->slug,
                'name' => $r->name,
                'description' => $r->description,
                'permissions' => array_map(fn (Permission $p) => ['name' => $p->value, 'label' => $p->label()], $r->permissions()),
            ]),
        ]);
    }
}
