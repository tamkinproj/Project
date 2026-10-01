<?php

namespace App\Modules\Identity\Models;

use App\Modules\Admin\Enums\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Card\Models\Card;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use App\Modules\Social\Models\Post;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected $fillable = ['email', 'password', 'status', 'status_changed_at', 'last_login_at'];

    protected $hidden = ['password'];

    /** @var list<Permission>|null */
    private ?array $resolvedPermissions = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => AccountStatus::class,
            'status_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function privateProfile(): HasOne
    {
        return $this->hasOne(PrivateProfile::class);
    }

    public function privacy(): HasOne
    {
        return $this->hasOne(PrivacySetting::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withPivot('assigned_by', 'created_at');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followee_id')->withPivot('created_at');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followee_id', 'follower_id')->withPivot('created_at');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blocks', 'blocker_id', 'blocked_id')->withPivot('created_at');
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function isFollowing(User $other): bool
    {
        return DB::table('follows')->where('follower_id', $this->id)->where('followee_id', $other->id)->exists();
    }

    public function hasBlocked(User $other): bool
    {
        return DB::table('blocks')->where('blocker_id', $this->id)->where('blocked_id', $other->id)->exists();
    }

    // True when either user has blocked the other. Blocking is always enforced in both directions.
    public function isBlockedWith(User $other): bool
    {
        return DB::table('blocks')
            ->where(fn ($q) => $q->where('blocker_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn ($q) => $q->where('blocker_id', $other->id)->where('blocked_id', $this->id))
            ->exists();
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return $this->resolvedPermissions ??= $this->resolvePermissions();
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function isAdmin(): bool
    {
        return $this->permissions() !== [];
    }

    public function forgetPermissions(): void
    {
        $this->resolvedPermissions = null;
    }

    /** @return list<Permission> */
    private function resolvePermissions(): array
    {
        $roles = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->pluck('roles.slug', 'roles.id');

        if ($roles->isEmpty()) {
            return [];
        }

        if ($roles->contains(Role::SUPER_ADMIN)) {
            return Permission::cases();
        }

        return DB::table('role_permissions')
            ->whereIn('role_id', $roles->keys())
            ->distinct()
            ->pluck('permission')
            ->map(fn (string $p) => Permission::tryFrom($p))
            ->filter()
            ->values()
            ->all();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
