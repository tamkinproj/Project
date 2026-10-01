<?php

namespace Tests\Feature;

use App\Modules\Card\Enums\CardStatus;
use App\Modules\Card\Models\Card;
use App\Modules\Card\Services\CardService;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardTest extends TestCase
{
    private const CHIP = '04:A2:2B:1A:9C:5D:80';

    /** @return array{card: Card, number: string, activation_code: string} */
    private function issue(?User $assignee = null, string $chip = self::CHIP): array
    {
        $officer = $this->adminWithRole('card_officer');

        $response = $this->actingWithToken($officer)->postJson('/api/v1/admin/cards', array_filter([
            'chip_uid' => $chip,
            'username' => $assignee ? $this->username($assignee) : null,
        ]))->assertCreated();

        return [
            'card' => Card::findOrFail($response->json('data.id')),
            'number' => str_replace(' ', '', $response->json('secrets.card_number')),
            'activation_code' => $response->json('secrets.activation_code'),
        ];
    }

    public function test_every_member_gets_a_digital_card(): void
    {
        $user = User::factory()->create();
        app(CardService::class)->createVirtualCard($user);

        $this->actingWithToken($user)->getJson('/api/v1/me/cards')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'virtual')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.actions.report_lost', false);
    }

    public function test_issuing_stores_only_hashes_and_shows_secrets_once(): void
    {
        $issued = $this->issue();

        $this->assertMatchesRegularExpression('/^\d{12}$/', $issued['number']);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{5}-[0-9A-HJKMNP-TV-Z]{5}$/', $issued['activation_code']);

        $row = (array) DB::table('cards')->where('id', $issued['card']->id)->first();
        $serialized = json_encode($row);
        $this->assertStringNotContainsString($issued['number'], $serialized);
        $this->assertStringNotContainsString(str_replace('-', '', $issued['activation_code']), $serialized);
        $this->assertStringNotContainsString('04A22B1A9C5D80', $serialized);
        $this->assertSame(substr($issued['number'], -4), $row['number_last4']);
        $this->assertSame('pending_activation', $row['status']);

        $officer = User::whereHas('roles')->first();
        $detail = $this->actingWithToken($officer)->getJson("/api/v1/admin/cards/{$issued['card']->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString(str_replace('-', '', $issued['activation_code']), str_replace('-', '', $detail));
    }

    public function test_the_same_chip_cannot_be_registered_twice(): void
    {
        $this->issue();
        $officer = User::whereHas('roles')->first();

        $this->actingWithToken($officer)->postJson('/api/v1/admin/cards', ['chip_uid' => '04a22b1a9c5d80'])
            ->assertUnprocessable()->assertJsonValidationErrors('chip_uid');
    }

    public function test_a_member_links_a_physical_card_with_code_and_last_four_digits(): void
    {
        $member = User::factory()->create();
        $issued = $this->issue();

        $this->actingWithToken($member)->postJson('/api/v1/me/cards/link', [
            'activation_code' => strtolower($issued['activation_code']),
            'last4' => substr($issued['number'], -4),
        ])->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.type', 'physical');

        $card = $issued['card']->fresh();
        $this->assertSame($member->id, $card->user_id);
        $this->assertNull($card->activation_code_hash);
        $this->assertDatabaseHas('card_events', ['card_id' => $card->id, 'event' => 'activated']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id, 'type' => 'security.card_linked']);
    }

    public function test_activation_codes_are_single_use(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $issued = $this->issue();
        $payload = ['activation_code' => $issued['activation_code'], 'last4' => substr($issued['number'], -4)];

        $this->actingWithToken($first)->postJson('/api/v1/me/cards/link', $payload)->assertOk();
        $this->actingWithToken($second)->postJson('/api/v1/me/cards/link', $payload)->assertUnprocessable();

        $this->assertSame($first->id, $issued['card']->fresh()->user_id);
    }

    public function test_wrong_last_four_expired_codes_and_other_peoples_cards_all_fail_identically(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $assigned = $this->issue($owner);
        $unassigned = $this->issue(chip: '04B33C2B0D6E91');

        $wrongDigits = $this->actingWithToken($intruder)->postJson('/api/v1/me/cards/link', [
            'activation_code' => $unassigned['activation_code'], 'last4' => substr($unassigned['number'], -4) === '0000' ? '1111' : '0000',
        ])->assertUnprocessable();

        $notTheirs = $this->actingWithToken($intruder)->postJson('/api/v1/me/cards/link', [
            'activation_code' => $assigned['activation_code'], 'last4' => substr($assigned['number'], -4),
        ])->assertUnprocessable();

        $this->assertSame($wrongDigits->json('errors'), $notTheirs->json('errors'));

        $unassigned['card']->forceFill(['activation_expires_at' => now()->subDay()])->save();
        $this->actingWithToken($intruder)->postJson('/api/v1/me/cards/link', [
            'activation_code' => $unassigned['activation_code'], 'last4' => substr($unassigned['number'], -4),
        ])->assertUnprocessable();

        $this->actingWithToken($owner)->postJson('/api/v1/me/cards/link', [
            'activation_code' => $assigned['activation_code'], 'last4' => substr($assigned['number'], -4),
        ])->assertOk();

        $this->assertSame(3, DB::table('audit_logs')->where('action', 'card.activation_failed')->count());
    }

    public function test_activation_attempts_are_rate_limited(): void
    {
        $member = User::factory()->create();

        foreach (range(1, 5) as $_) {
            $this->actingWithToken($member)->postJson('/api/v1/me/cards/link', ['activation_code' => 'AAAAA-AAAAA', 'last4' => '1234'])->assertUnprocessable();
        }

        $this->actingWithToken($member)->postJson('/api/v1/me/cards/link', ['activation_code' => 'AAAAA-AAAAA', 'last4' => '1234'])->assertStatus(429);
    }

    public function test_freeze_unfreeze_and_report_lost_lifecycle(): void
    {
        $member = User::factory()->create();
        $issued = $this->issue($member);
        $this->actingWithToken($member)->postJson('/api/v1/me/cards/link', [
            'activation_code' => $issued['activation_code'], 'last4' => substr($issued['number'], -4),
        ])->assertOk();
        $id = $issued['card']->id;

        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$id}/freeze")->assertOk()->assertJsonPath('data.status', 'frozen');
        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$id}/freeze")->assertStatus(409);
        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$id}/unfreeze")->assertOk()->assertJsonPath('data.status', 'active');

        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$id}/report-lost")
            ->assertOk()
            ->assertJsonPath('data.status', 'lost')
            ->assertJsonPath('data.actions.unfreeze', false);

        // Lost is permanent.
        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$id}/unfreeze")->assertStatus(409);
        $this->assertNotNull($issued['card']->fresh()->replacement_requested_at);

        $events = $this->actingWithToken($member)->getJson("/api/v1/me/cards/{$id}/events")->json('data.*.event');
        $this->assertSame(['reported_lost', 'unfrozen', 'frozen', 'activated', 'issued'], $events);
    }

    public function test_members_cannot_touch_other_members_cards(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $card = app(CardService::class)->createVirtualCard($owner);

        foreach (['freeze', 'unfreeze', 'report-lost', 'request-replacement'] as $action) {
            $this->actingWithToken($intruder)->postJson("/api/v1/me/cards/{$card->id}/{$action}")->assertNotFound();
        }
        $this->actingWithToken($intruder)->getJson("/api/v1/me/cards/{$card->id}/events")->assertNotFound();

        $this->assertSame(CardStatus::Active, $card->fresh()->status);
    }

    public function test_digital_cards_cannot_be_reported_lost(): void
    {
        $member = User::factory()->create();
        $card = app(CardService::class)->createVirtualCard($member);

        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$card->id}/report-lost")->assertStatus(409);
    }

    public function test_chip_resolution_only_identifies_active_cards_of_active_members(): void
    {
        $member = User::factory()->create();
        $issued = $this->issue($member);
        $cards = app(CardService::class);

        $this->assertNull($cards->resolveChip(self::CHIP), 'pending cards must not resolve');

        $cards->activate($member, $issued['activation_code'], substr($issued['number'], -4));
        $this->assertSame($issued['card']->id, $cards->resolveChip('04a22b1a9c5d80')?->id);

        $cards->freeze($member, $issued['card']);
        $this->assertNull($cards->resolveChip(self::CHIP), 'frozen cards must not resolve');

        $cards->unfreeze($member, $issued['card']);
        $member->forceFill(['status' => 'suspended'])->save();
        $this->assertNull($cards->resolveChip(self::CHIP), 'cards of suspended members must not resolve');
    }

    public function test_replacement_card_retires_the_old_one_on_activation(): void
    {
        $member = User::factory()->create();
        $original = $this->issue($member);
        $cards = app(CardService::class);
        $cards->activate($member, $original['activation_code'], substr($original['number'], -4));
        $this->actingWithToken($member)->postJson("/api/v1/me/cards/{$original['card']->id}/request-replacement")->assertOk();

        $officer = User::whereHas('roles')->first();
        $this->actingWithToken($officer)->getJson('/api/v1/admin/cards?replacement_requested=1')->assertJsonPath('data.0.id', $original['card']->id);

        $response = $this->actingWithToken($officer)->postJson('/api/v1/admin/cards', [
            'chip_uid' => '04C44D3C1E7FA2',
            'replaces_card_id' => $original['card']->id,
        ])->assertCreated()->assertJsonPath('data.holder.id', $member->id);

        $cards->activate($member, $response->json('secrets.activation_code'), substr(str_replace(' ', '', $response->json('secrets.card_number')), -4));

        $this->assertSame(CardStatus::Replaced, $original['card']->fresh()->status);
    }

    public function test_admin_revocation_notifies_the_holder(): void
    {
        $member = User::factory()->create();
        $card = app(CardService::class)->createVirtualCard($member);
        $officer = $this->adminWithRole('card_officer');

        $this->actingWithToken($officer)->postJson("/api/v1/admin/cards/{$card->id}/revoke", ['reason' => 'Fraud investigation'])
            ->assertOk()->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id, 'type' => 'security.card_revoked']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'card.revoked', 'actor_id' => $officer->id, 'subject_id' => $card->id]);
    }

    public function test_card_endpoints_require_card_permissions(): void
    {
        $moderator = $this->adminWithRole('moderator');
        $member = User::factory()->create();

        $this->actingWithToken($member)->getJson('/api/v1/admin/cards')->assertForbidden();
        $this->actingWithToken($moderator)->getJson('/api/v1/admin/cards')->assertForbidden();
        $this->actingWithToken($moderator)->postJson('/api/v1/admin/cards', ['chip_uid' => self::CHIP])->assertForbidden();
    }
}
