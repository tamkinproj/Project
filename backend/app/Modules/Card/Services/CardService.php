<?php

namespace App\Modules\Card\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Card\Enums\CardStatus;
use App\Modules\Card\Enums\CardType;
use App\Modules\Card\Models\Card;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\SecurityAlert;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Card lifecycle. A card is an identity credential only: it holds no balance and no
 * personal data. Every transition locks the row, records a card event and an audit entry.
 *
 *   pending_activation ──activate──▶ active ◀──unfreeze── frozen
 *                                      │  └────freeze─────▶ │
 *                                      └──report lost──▶ lost ◀─┘
 *   any non-terminal ──revoke──▶ revoked     active (old) ──new card activated──▶ replaced
 */
class CardService
{
    public function __construct(
        private readonly CardSecrets $secrets,
        private readonly AuditLogger $audit,
        private readonly Request $request,
    ) {}

    public function createVirtualCard(User $user): Card
    {
        return $this->createWithUniqueNumber(function (string $number) use ($user) {
            $card = new Card([
                'type' => CardType::Virtual,
                'status' => CardStatus::Active,
                'number_last4' => substr($number, -4),
                'number_hash' => $this->secrets->hashNumber($number),
                'activated_at' => now(),
            ]);
            $card->holder()->associate($user);
            $card->save();

            $this->event($card, 'issued', $user, ['type' => 'virtual']);

            return $card;
        });
    }

    /**
     * Registers a physical card. Returns the full card number and activation code — the only
     * time they exist in plain text — so they can be printed on the card mailer.
     *
     * @return array{card: Card, number: string, activation_code: string}
     */
    public function issuePhysical(User $issuer, string $chipUid, ?User $assignee = null, ?Card $replaces = null): array
    {
        $chipHash = $this->secrets->hashChipUid($chipUid);

        if (Card::where('chip_uid_hash', $chipHash)->exists()) {
            throw ValidationException::withMessages(['chip_uid' => 'This chip is already registered to a card.']);
        }

        if ($replaces && (! $replaces->isPhysical() || ($assignee && $replaces->user_id !== $assignee->id))) {
            throw ValidationException::withMessages(['replaces_card_id' => 'The card being replaced must be a physical card belonging to the same member.']);
        }

        $code = $this->secrets->generateActivationCode();

        return $this->createWithUniqueNumber(function (string $number) use ($issuer, $chipHash, $assignee, $replaces, $code) {
            $card = new Card([
                'type' => CardType::Physical,
                'status' => CardStatus::PendingActivation,
                'number_last4' => substr($number, -4),
                'number_hash' => $this->secrets->hashNumber($number),
                'chip_uid_hash' => $chipHash,
                'activation_code_hash' => $this->secrets->hashActivationCode($code),
                'activation_expires_at' => now()->addDays(config('ecosystem.card.activation_code_ttl_days')),
            ]);
            $card->holder()->associate($assignee);
            $card->issuer()->associate($issuer);
            $card->replaces()->associate($replaces);
            $card->save();

            $this->event($card, 'issued', $issuer, ['type' => 'physical', 'assigned' => (bool) $assignee, 'replaces' => $replaces?->id]);
            $this->audit->record('card.issued', actor: $issuer, subject: $card, metadata: ['assigned_to' => $assignee?->id]);

            return ['card' => $card, 'number' => $number, 'activation_code' => $code];
        });
    }

    // Links a physical card to the user. Every failure looks identical so codes cannot be probed.
    public function activate(User $user, string $activationCode, string $last4): Card
    {
        $card = DB::transaction(function () use ($user, $activationCode, $last4) {
            $card = Card::where('activation_code_hash', $this->secrets->hashActivationCode($activationCode))
                ->where('status', CardStatus::PendingActivation)
                ->lockForUpdate()
                ->first();

            $valid = $card
                && hash_equals($card->number_last4, $last4)
                && $card->activation_expires_at?->isFuture()
                && ($card->user_id === null || $card->user_id === $user->id);

            if (! $valid) {
                return null;
            }

            $card->holder()->associate($user);
            $card->fill([
                'status' => CardStatus::Active,
                'activated_at' => now(),
                'activation_code_hash' => null,
                'activation_expires_at' => null,
            ])->save();

            if ($card->replaces_card_id) {
                $old = Card::whereKey($card->replaces_card_id)->lockForUpdate()->first();

                if ($old && ! $old->status->isTerminal()) {
                    $old->fill(['status' => CardStatus::Replaced, 'revoked_at' => now()])->save();
                    $this->event($old, 'replaced', $user, ['by' => $card->id]);
                }
            }

            $this->event($card, 'activated', $user);
            $this->audit->record('card.activated', actor: $user, subject: $card);

            return $card;
        });

        // Recorded outside the transaction so the failed attempt is not rolled back with it.
        if (! $card) {
            $this->audit->record('card.activation_failed', actor: $user);

            throw ValidationException::withMessages(['activation_code' => 'That activation code and card number do not match an available card.']);
        }

        $user->notify(new SecurityAlert('card_linked', "A physical card ending in {$card->number_last4} was linked to your account.", sendEmail: true));

        return $card;
    }

    public function freeze(User $user, Card $card): Card
    {
        return $this->transition($user, $card, [CardStatus::Active], CardStatus::Frozen, 'frozen', ['frozen_at' => now()],
            "Your card ending in {$card->number_last4} was frozen.", email: false);
    }

    public function unfreeze(User $user, Card $card): Card
    {
        return $this->transition($user, $card, [CardStatus::Frozen], CardStatus::Active, 'unfrozen', ['frozen_at' => null],
            "Your card ending in {$card->number_last4} was unfrozen.", email: true);
    }

    public function reportLost(User $user, Card $card): Card
    {
        if (! $card->isPhysical()) {
            throw new ConflictHttpException('Only physical cards can be reported lost. Freeze your digital card instead.');
        }

        return $this->transition($user, $card, [CardStatus::Active, CardStatus::Frozen], CardStatus::Lost, 'reported_lost',
            ['lost_reported_at' => now(), 'replacement_requested_at' => $card->replacement_requested_at ?? now()],
            "Your card ending in {$card->number_last4} was reported lost and permanently disabled. A replacement has been requested.", email: true);
    }

    public function requestReplacement(User $user, Card $card): Card
    {
        if (! $card->isPhysical()) {
            throw new ConflictHttpException('Digital cards cannot be replaced.');
        }

        return DB::transaction(function () use ($user, $card) {
            $card = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (! in_array($card->status, [CardStatus::Active, CardStatus::Frozen, CardStatus::Lost], true)) {
                throw new ConflictHttpException('A replacement cannot be requested for this card.');
            }

            if (! $card->replacement_requested_at) {
                $card->forceFill(['replacement_requested_at' => now()])->save();
                $this->event($card, 'replacement_requested', $user);
                $this->audit->record('card.replacement_requested', actor: $user, subject: $card);
            }

            return $card;
        });
    }

    public function revoke(User $admin, Card $card, string $reason): Card
    {
        $card = DB::transaction(function () use ($admin, $card, $reason) {
            $card = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();

            if ($card->status === CardStatus::Revoked) {
                return $card;
            }

            $card->fill(['status' => CardStatus::Revoked, 'revoked_at' => now(), 'activation_code_hash' => null])->save();
            $this->event($card, 'revoked', $admin, ['reason' => $reason]);
            $this->audit->record('card.revoked', actor: $admin, subject: $card, metadata: ['reason' => $reason]);

            return $card;
        });

        $card->holder?->notify(new SecurityAlert('card_revoked', "Your card ending in {$card->number_last4} was revoked by an administrator.", sendEmail: true));

        return $card;
    }

    public function revokeAllFor(User $user, string $reason): void
    {
        Card::where('user_id', $user->id)
            ->where('status', '!=', CardStatus::Revoked)
            ->lockForUpdate()
            ->get()
            ->each(function (Card $card) use ($reason) {
                $card->fill(['status' => CardStatus::Revoked, 'revoked_at' => now(), 'activation_code_hash' => null])->save();
                $this->event($card, 'revoked', null, ['reason' => $reason]);
            });
    }

    /**
     * Identifies the active card for a chip UID read by a terminal. This is identification
     * only: a UID can be cloned, so no payment or access decision may rely on it alone.
     * Phase 4 adds terminal authentication and cryptographic card authentication on top.
     */
    public function resolveChip(string $chipUid): ?Card
    {
        return Card::where('chip_uid_hash', $this->secrets->hashChipUid($chipUid))
            ->where('status', CardStatus::Active)
            ->whereHas('holder', fn ($q) => $q->where('status', 'active'))
            ->first();
    }

    /** @param list<CardStatus> $from */
    private function transition(User $user, Card $card, array $from, CardStatus $to, string $event, array $changes, string $message, bool $email): Card
    {
        $card = DB::transaction(function () use ($user, $card, $from, $to, $event, $changes) {
            $card = Card::whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (! in_array($card->status, $from, true)) {
                throw new ConflictHttpException("This card cannot be {$this->describe($event)} while it is {$this->describe($card->status->value)}.");
            }

            $card->fill([...$changes, 'status' => $to])->save();
            $this->event($card, $event, $user);
            $this->audit->record("card.{$event}", actor: $user, subject: $card);

            return $card;
        });

        $user->notify(new SecurityAlert("card_{$event}", $message, sendEmail: $email));

        return $card;
    }

    private function event(Card $card, string $event, ?User $actor, array $metadata = []): void
    {
        $card->events()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'ip_address' => $this->request->ip(),
            'metadata' => array_filter($metadata, fn ($v) => $v !== null) ?: null,
        ]);
    }

    private function createWithUniqueNumber(callable $create): mixed
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(fn () => $create($this->secrets->generateNumber()));
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'number_hash')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a unique card number.');
    }

    private function describe(string $value): string
    {
        return str_replace('_', ' ', $value);
    }
}
