<?php

namespace Tests\Feature;

use App\Modules\Card\Enums\CardStatus;
use App\Modules\Card\Services\CardService;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Social\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAndPrivacyTest extends TestCase
{
    public function test_sessions_list_marks_the_current_device(): void
    {
        $user = User::factory()->create();
        $this->tokenFor($user, 'Phone');
        $laptop = $this->tokenFor($user, 'Laptop');

        $response = $this->withBearer($laptop)->getJson('/api/v1/me/sessions')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $current = collect($response->json('data'))->firstWhere('is_current', true);
        $this->assertSame('Laptop', $current['device_name']);
    }

    public function test_a_user_cannot_revoke_another_users_session(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $this->tokenFor($victim);
        $victimSessionId = $victim->tokens()->sole()->id;

        $this->actingWithToken($attacker)->deleteJson("/api/v1/me/sessions/{$victimSessionId}")->assertNotFound();

        $this->assertSame(1, $victim->tokens()->count());
    }

    public function test_sign_out_other_devices_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $other = $this->tokenFor($user, 'Phone');
        $current = $this->tokenFor($user, 'Laptop');

        $this->withBearer($current)->deleteJson('/api/v1/me/sessions')->assertOk()->assertJsonPath('revoked', 1);

        $this->withBearer($other)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withBearer($current)->getJson('/api/v1/me')->assertOk();
    }

    public function test_changing_password_requires_the_current_one_and_signs_out_other_devices(): void
    {
        $user = User::factory()->create();
        $other = $this->tokenFor($user, 'Phone');
        $current = $this->tokenFor($user, 'Laptop');

        $this->withBearer($current)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong-password-x',
            'password' => 'brand-new-pass-456',
            'password_confirmation' => 'brand-new-pass-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withBearer($current)->putJson('/api/v1/me/password', [
            'current_password' => 'correct-horse-battery',
            'password' => 'brand-new-pass-456',
            'password_confirmation' => 'brand-new-pass-456',
        ])->assertOk();

        $this->withBearer($other)->getJson('/api/v1/me')->assertUnauthorized();
        $this->withBearer($current)->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'type' => 'security.password_changed']);
    }

    public function test_public_profile_never_exposes_private_information(): void
    {
        $owner = User::factory()->create(['email' => 'secret@example.com']);
        $owner->privateProfile()->create(['legal_name' => 'Zayd ibn Harithah', 'phone' => '+639171234567', 'date_of_birth' => '1990-01-01']);
        $owner->profile->update(['location' => 'Cotabato City']);
        $viewer = User::factory()->create();

        $response = $this->actingWithToken($viewer)->getJson('/api/v1/profiles/'.$this->username($owner))->assertOk();

        $json = $response->getContent();
        foreach (['secret@example.com', 'Zayd ibn Harithah', '+639171234567', '1990-01-01', $owner->id] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $response->assertJsonPath('data.location', 'Cotabato City');

        $owner->privacy->update(['show_location' => false]);
        $this->actingWithToken($viewer)->getJson('/api/v1/profiles/'.$this->username($owner))->assertJsonPath('data.location', null);
    }

    public function test_private_profile_is_encrypted_at_rest_and_only_visible_to_its_owner(): void
    {
        $user = User::factory()->create();

        $this->actingWithToken($user)->patchJson('/api/v1/me/private-profile', [
            'legal_name' => 'Abdullah Hamza',
            'phone' => '+639171234567',
        ])->assertOk()->assertJsonPath('data.legal_name', 'Abdullah Hamza');

        $raw = DB::table('private_profiles')->where('user_id', $user->id)->first();
        $this->assertNotSame('Abdullah Hamza', $raw->legal_name);
        $this->assertStringNotContainsString('639171234567', $raw->phone);

        $audit = DB::table('audit_logs')->where('action', 'profile.private_updated')->sole();
        $this->assertStringNotContainsString('Abdullah', $audit->metadata);
    }

    public function test_search_respects_the_searchable_setting_and_blocks(): void
    {
        $viewer = User::factory()->create();
        $visible = User::factory()->create();
        $hidden = User::factory()->create();
        $blocker = User::factory()->create();
        $visible->profile->update(['display_name' => 'Abu Hamza']);
        $hidden->profile->update(['display_name' => 'Abu Hidden']);
        $blocker->profile->update(['display_name' => 'Abu Blocker']);
        $hidden->privacy->update(['profile_searchable' => false]);
        DB::table('blocks')->insert(['blocker_id' => $blocker->id, 'blocked_id' => $viewer->id, 'created_at' => now()]);

        $names = collect($this->actingWithToken($viewer)->getJson('/api/v1/profiles/search?q=abu')->assertOk()->json('data'))->pluck('display_name');

        $this->assertEquals(['Abu Hamza'], $names->all());
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create();

        $this->actingWithToken($viewer)->getJson('/api/v1/profiles/search?q=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_profiles_of_suspended_users_and_blockers_are_not_found(): void
    {
        $viewer = User::factory()->create();
        $suspended = User::factory()->suspended()->create();
        $blocker = User::factory()->create();
        DB::table('blocks')->insert(['blocker_id' => $blocker->id, 'blocked_id' => $viewer->id, 'created_at' => now()]);

        $this->actingWithToken($viewer)->getJson('/api/v1/profiles/'.$this->username($suspended))->assertNotFound();
        $this->actingWithToken($viewer)->getJson('/api/v1/profiles/'.$this->username($blocker))->assertNotFound();
    }

    public function test_avatar_uploads_are_re_encoded_and_old_files_removed(): void
    {
        $user = User::factory()->create();

        $this->actingWithToken($user)->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 600)], ['Accept' => 'application/json'])->assertOk();
        $first = $user->profile->fresh()->avatar_path;
        $this->assertStringEndsWith('.webp', $first);
        Storage::disk('media')->assertExists($first);

        $this->actingWithToken($user)->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me2.png', 300, 300)], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('media')->assertMissing($first);
    }

    public function test_non_image_avatar_is_rejected(): void
    {
        $user = User::factory()->create();
        $fake = UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "pwned";');

        $this->actingWithToken($user)->post('/api/v1/me/avatar', ['avatar' => $fake], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_deactivation_requires_password_and_signs_out_everywhere(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withBearer($token)->postJson('/api/v1/me/deactivate', ['password' => 'wrong-password-x'])->assertUnprocessable();
        $this->withBearer($token)->postJson('/api/v1/me/deactivate', ['password' => 'correct-horse-battery'])->assertNoContent();

        $this->assertSame(AccountStatus::Deactivated, $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_account_deletion_removes_data_revokes_cards_and_fixes_counters(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        app(CardService::class)->createVirtualCard($user);
        $othersPost = Post::factory()->for($other, 'author')->create();
        $this->actingWithToken($user)->postJson("/api/v1/posts/{$othersPost->id}/comments", ['body' => 'Nice'])->assertCreated();
        $this->actingWithToken($user)->putJson("/api/v1/posts/{$othersPost->id}/reaction")->assertOk();
        $card = $user->cards()->sole();

        $this->actingWithToken($user)->deleteJson('/api/v1/me', ['password' => 'correct-horse-battery'])->assertUnprocessable();
        $this->actingWithToken($user)->deleteJson('/api/v1/me', ['password' => 'correct-horse-battery', 'confirmation' => 'DELETE'])->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('profiles', ['user_id' => $user->id]);
        $this->assertSame(CardStatus::Revoked, $card->fresh()->status);
        $this->assertSame(0, $othersPost->fresh()->comments_count);
        $this->assertSame(0, $othersPost->fresh()->reactions_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.deleted', 'subject_id' => $user->id]);
    }
}
