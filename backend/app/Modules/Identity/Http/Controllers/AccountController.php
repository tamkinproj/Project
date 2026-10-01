<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Identity\Http\Requests\ConfirmPasswordRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Services\DeleteAccount;
use App\Modules\Notifications\Notifications\SecurityAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): MeResource
    {
        return new MeResource($request->user()->load('profile', 'privacy'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        $revoked = $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        $this->audit->record('account.password_changed', subject: $user, metadata: ['other_sessions_revoked' => $revoked]);
        $user->notify(new SecurityAlert('password_changed', 'Your password was changed and your other devices were signed out.', sendEmail: true));

        return response()->json(['message' => 'Password updated. Other devices have been signed out.']);
    }

    public function deactivate(ConfirmPasswordRequest $request): Response
    {
        $user = $request->user();
        $user->forceFill(['status' => AccountStatus::Deactivated, 'status_changed_at' => now()])->save();
        $user->tokens()->delete();

        $this->audit->record('account.deactivated', subject: $user);

        return response()->noContent();
    }

    public function destroy(ConfirmPasswordRequest $request, DeleteAccount $deleteAccount): Response
    {
        $deleteAccount->handle($request->user());

        return response()->noContent();
    }
}
