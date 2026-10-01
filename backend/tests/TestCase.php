<?php

namespace Tests;

use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\RoleAssignment;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    // Authenticates with a real bearer token (not a mocked one), exactly like a client would.
    protected function actingWithToken(User $user, string $device = 'Test device'): static
    {
        return $this->withToken($this->tokenFor($user, $device));
    }

    protected function tokenFor(User $user, string $device = 'Test device'): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken($device, ['*'], now()->addDay())->plainTextToken;
    }

    protected function withBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function adminWithRole(string $role): User
    {
        $this->seed(RolesSeeder::class);
        $user = User::factory()->create();
        app(RoleAssignment::class)->grant(null, $user, Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    protected function username(User $user): string
    {
        return $user->profile()->value('username');
    }
}
