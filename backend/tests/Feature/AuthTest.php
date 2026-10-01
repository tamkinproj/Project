<?php

namespace Tests\Feature;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Card\Enums\CardType;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function registerPayload(array $overrides = []): array
    {
        return [
            'email' => 'Ibn.Zayn@Example.com',
            'password' => 'a-strong-pass-123',
            'password_confirmation' => 'a-strong-pass-123',
            'username' => '@IbnZayn',
            'display_name' => 'Ibn Zayn',
            ...$overrides,
        ];
    }

    public function test_registration_creates_separated_identity_records_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ibn.zayn@example.com')
            ->assertJsonPath('user.profile.username', 'ibnzayn')
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonMissingPath('user.password');

        $user = User::where('email', 'ibn.zayn@example.com')->firstOrFail();
        $this->assertNotNull($user->profile);
        $this->assertNotNull($user->privacy);
        $this->assertSame(CardType::Virtual, $user->cards()->sole()->type);
        $this->assertNotSame('a-strong-pass-123', $user->password);
        $this->assertStringContainsString('|', $response->json('token'));
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.registered', 'actor_id' => $user->id]);
    }

    public function test_registration_rejects_reserved_duplicate_and_malformed_usernames(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['username' => 'admin']))
            ->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['username' => 'bad name!']))
            ->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['username' => 'a..b']))
            ->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->registerPayload(['email' => 'other@example.com', 'username' => 'IBNZAYN']))
            ->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_registration_requires_a_reasonable_password(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_issues_a_token_and_records_the_device(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0')
            ->postJson('/api/v1/auth/login', ['email' => 'MEMBER@example.com', 'password' => 'correct-horse-battery']);

        $response->assertOk()->assertJsonPath('user.id', $user->id);

        $token = $user->tokens()->sole();
        $this->assertSame('Chrome on Windows', $token->name);
        $this->assertSame('127.0.0.1', $token->ip_address);
        $this->assertNotNull($token->expires_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);
    }

    public function test_wrong_password_and_unknown_email_give_the_same_error(): void
    {
        User::factory()->create(['email' => 'member@example.com']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'member@example.com', 'password' => 'nope-nope-nope']);
        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope-nope-nope']);

        $wrongPassword->assertUnprocessable();
        $unknownEmail->assertUnprocessable();
        $this->assertSame($wrongPassword->json('errors'), $unknownEmail->json('errors'));
    }

    public function test_account_is_locked_after_repeated_failures_from_any_ip(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        foreach (range(1, 10) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/login', ['email' => 'member@example.com', 'password' => 'wrong-password-x']);
        }

        // Even the correct password is refused while locked.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/v1/auth/login', ['email' => 'member@example.com', 'password' => 'correct-horse-battery'])
            ->assertStatus(429);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'type' => 'security.account_locked']);
    }

    public function test_suspended_accounts_cannot_sign_in(): void
    {
        User::factory()->suspended()->create(['email' => 'banned@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'banned@example.com', 'password' => 'correct-horse-battery'])
            ->assertForbidden();
    }

    public function test_signing_in_reactivates_a_deactivated_account(): void
    {
        $user = User::factory()->deactivated()->create(['email' => 'away@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'away@example.com', 'password' => 'correct-horse-battery'])->assertOk();

        $this->assertSame(AccountStatus::Active, $user->fresh()->status);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $phone = $this->tokenFor($user, 'Phone');
        $laptop = $this->tokenFor($user, 'Laptop');

        $this->withBearer($laptop)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->withBearer($laptop)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withBearer($phone)->getJson('/api/v1/me')->assertOk();
    }

    public function test_invalid_and_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->withBearer('not-a-token')->getJson('/api/v1/me')->assertUnauthorized();
        $this->withBearer('01h0000000000000000000000|forged')->getJson('/api/v1/me')->assertUnauthorized();

        $expired = $user->createToken('Old', ['*'], now()->subMinute())->plainTextToken;
        $this->withBearer($expired)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_tokens_stop_working_once_an_account_is_suspended(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $user->forceFill(['status' => AccountStatus::Suspended])->save();

        $this->withBearer($token)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'account_inactive');
    }

    public function test_email_verification_requires_a_valid_signature_and_matching_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $hash = sha1($user->email);
        $signed = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => $hash], absolute: false);

        $this->getJson(str_replace('signature=', 'signature=0', $signed))->assertForbidden();
        $this->getJson(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.com')], absolute: false))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->getJson($signed)->assertOk()->assertJsonPath('verified', true);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_links_to_the_web_app(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://app.test/verify-email?') && str_contains($url, 'signature=');
        });
    }

    public function test_unverified_users_cannot_create_content(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingWithToken($user)->postJson('/api/v1/posts', ['body' => 'Hello'])->assertForbidden();
    }

    public function test_forgot_password_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'member@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'member@example.com']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_revokes_every_session(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $token = $this->tokenFor($user);
        $resetToken = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'member@example.com',
            'token' => $resetToken,
            'password' => 'brand-new-pass-456',
            'password_confirmation' => 'brand-new-pass-456',
        ])->assertOk();

        $this->withBearer($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'member@example.com', 'password' => 'brand-new-pass-456'])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'auth.password_reset')->where('subject_id', $user->id)->exists());
    }

    public function test_password_reset_with_a_bad_token_fails(): void
    {
        User::factory()->create(['email' => 'member@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'member@example.com',
            'token' => 'forged',
            'password' => 'brand-new-pass-456',
            'password_confirmation' => 'brand-new-pass-456',
        ])->assertUnprocessable();
    }
}
