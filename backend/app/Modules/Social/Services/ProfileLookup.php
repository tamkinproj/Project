<?php

namespace App\Modules\Social\Services;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileLookup
{
    // Inactive accounts, and people who have blocked the viewer, are indistinguishable from non-existent ones.
    public function find(string $username, User $viewer, bool $ignoreBlocks = false): User
    {
        $user = User::query()
            ->where('status', AccountStatus::Active)
            ->whereHas('profile', fn ($q) => $q->where('username', mb_strtolower(ltrim($username, '@'))))
            ->with(['profile', 'privacy'])
            ->first();

        if (! $user || (! $ignoreBlocks && ! $user->is($viewer) && $user->hasBlocked($viewer))) {
            throw new NotFoundHttpException;
        }

        return $user;
    }
}
