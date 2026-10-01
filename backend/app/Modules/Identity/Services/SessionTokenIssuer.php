<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SessionTokenIssuer
{
    // Returns the plain-text bearer token. Only its SHA-256 hash is stored.
    public function issue(User $user, Request $request, ?string $deviceName = null): string
    {
        $userAgent = mb_substr((string) $request->userAgent(), 0, 512);
        $name = Str::limit(trim((string) $deviceName) ?: $this->describeDevice($userAgent), 97);

        $newToken = $user->createToken($name, ['*'], now()->addMinutes(config('ecosystem.auth.token_ttl_minutes')));

        $newToken->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent ?: null,
        ])->save();

        return $newToken->plainTextToken;
    }

    private function describeDevice(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            str_contains($userAgent, 'Dart/') => 'Mobile app',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }
}
