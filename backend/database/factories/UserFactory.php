<?php

namespace Database\Factories;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('correct-horse-battery'),
            'status' => AccountStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->profile()->create([
                'username' => 'user_'.Str::lower(Str::random(10)),
                'display_name' => fake()->firstName(),
            ]);
            $user->privacy()->create();
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::Suspended]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::Deactivated]);
    }
}
