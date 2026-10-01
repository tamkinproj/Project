<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

// One row per signed-in device session.
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;

    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'ip_address', 'user_agent'];
}
