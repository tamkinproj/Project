<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Services\AuthenticateUser;
use App\Modules\Identity\Services\RegisterUser;
use App\Modules\Identity\Services\SessionTokenIssuer;
use App\Modules\Notifications\Notifications\SecurityAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController
{
    public function __construct(
        private readonly SessionTokenIssuer $tokens,
        private readonly AuditLogger $audit,
    ) {}

    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->safe()->only(['email', 'password', 'username', 'display_name']));
        $token = $this->tokens->issue($user, $request, $request->validated('device_name'));

        $this->audit->record('auth.registered', actor: $user, subject: $user);

        return response()->json([
            'token' => $token,
            'user' => new MeResource($user->load('profile', 'privacy')),
        ], 201);
    }

    public function login(LoginRequest $request, AuthenticateUser $authenticate): JsonResponse
    {
        $user = $authenticate->attempt($request->validated('email'), $request->validated('password'));
        $token = $this->tokens->issue($user, $request, $request->validated('device_name'));

        $this->audit->record('auth.login', actor: $user, subject: $user);
        $user->notify(new SecurityAlert('new_sign_in', 'New sign-in to your account from '.($request->validated('device_name') ?: 'a new device').'.'));

        return response()->json([
            'token' => $token,
            'user' => new MeResource($user->load('profile', 'privacy')),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();
        $this->audit->record('auth.logout');

        return response()->noContent();
    }
}
