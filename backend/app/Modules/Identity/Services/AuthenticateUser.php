<?php

namespace App\Modules\Identity\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\SecurityAlert;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticateUser
{
    // Checked when the email is unknown so response time does not reveal whether an account exists.
    private const TIMING_HASH = '$2y$12$po9A/zrO.CAgiNEIpeSdeuDIEiYKUOnoAhC80DiQ7h6UACWIf/6ae';

    public function __construct(private readonly AuditLogger $audit) {}

    public function attempt(string $email, string $password): User
    {
        $email = mb_strtolower(trim($email));
        $lockKey = 'account-lock:'.sha1($email);
        $maxAttempts = config('ecosystem.auth.lockout_attempts');

        if (RateLimiter::tooManyAttempts($lockKey, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($lockKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Too many failed sign-in attempts. Try again in {$minutes} minute(s).",
            ])->status(429);
        }

        $user = User::where('email', $email)->first();
        $valid = $user
            ? Hash::check($password, $user->password)
            : (password_verify($password, self::TIMING_HASH) && false);

        if (! $valid) {
            RateLimiter::hit($lockKey, config('ecosystem.auth.lockout_minutes') * 60);

            if ($user) {
                $this->audit->record('auth.login_failed', actor: null, subject: $user);

                if (RateLimiter::attempts($lockKey) === $maxAttempts) {
                    $user->notify(new SecurityAlert('account_locked', 'Your account was temporarily locked after repeated failed sign-in attempts.'));
                }
            }

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if ($user->status === AccountStatus::Suspended) {
            $this->audit->record('auth.login_blocked', actor: $user, subject: $user, metadata: ['reason' => 'suspended']);

            throw new HttpException(403, 'This account has been suspended. Contact support if you believe this is a mistake.');
        }

        if ($user->status === AccountStatus::Deactivated) {
            $user->status = AccountStatus::Active;
            $user->status_changed_at = now();
            $this->audit->record('account.reactivated', actor: $user, subject: $user);
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $password;
        }

        RateLimiter::clear($lockKey);
        $user->last_login_at = now();
        $user->save();

        return $user;
    }
}
