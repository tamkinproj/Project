<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Http\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\SecurityAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController
{
    public function __construct(private readonly AuditLogger $audit) {}

    // Same response whether or not the account exists, so emails cannot be enumerated.
    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => $request->validated('email')]);

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();

                $this->audit->record('auth.password_reset', actor: $user, subject: $user);
                $user->notify(new SecurityAlert('password_reset', 'Your password was reset and all devices were signed out.', sendEmail: true));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This password reset link is invalid or has expired.']);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }
}
