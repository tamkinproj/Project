<?php

namespace App\Modules\Identity\Services;

use App\Modules\Card\Services\CardService;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function __construct(private readonly CardService $cards) {}

    /** @param array{email: string, password: string, username: string, display_name: string} $data */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => AccountStatus::Active,
            ]);

            $user->profile()->create([
                'username' => $data['username'],
                'display_name' => $data['display_name'],
            ]);

            $user->privacy()->create();
            $this->cards->createVirtualCard($user);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }
}
