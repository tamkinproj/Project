<?php

namespace App\Modules\Card\Http\Controllers\Admin;

use App\Modules\Card\Enums\CardStatus;
use App\Modules\Card\Enums\CardType;
use App\Modules\Card\Models\Card;
use App\Modules\Card\Models\CardEvent;
use App\Modules\Card\Services\CardSecrets;
use App\Modules\Card\Services\CardService;
use App\Modules\Social\Services\ProfileLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardAdminController
{
    public function __construct(private readonly CardService $cards) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(CardStatus::class)],
            'type' => ['nullable', Rule::enum(CardType::class)],
            'replacement_requested' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $cards = Card::query()
            ->with(['holder.profile', 'issuer.profile'])
            ->where('type', $data['type'] ?? CardType::Physical->value)
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('replacement_requested'), fn ($q) => $q
                ->whereNotNull('replacement_requested_at')
                ->whereNotIn('status', [CardStatus::Replaced, CardStatus::Revoked]))
            ->when($data['q'] ?? null, fn ($q, $term) => preg_match('/^\d{4}$/', $term)
                ? $q->where('number_last4', $term)
                : $q->whereHas('holder.profile', fn ($p) => $p->where('username', mb_strtolower(ltrim($term, '@')))))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'data' => $cards->getCollection()->map(fn (Card $c) => $this->present($c)),
            'meta' => ['current_page' => $cards->currentPage(), 'last_page' => $cards->lastPage(), 'total' => $cards->total()],
        ]);
    }

    public function show(string $card): JsonResponse
    {
        $model = Card::with(['holder.profile', 'issuer.profile', 'events'])->findOrFail($card);

        return response()->json(['data' => [
            ...$this->present($model),
            'events' => $model->events->map(fn (CardEvent $e) => [
                'event' => $e->event,
                'actor_id' => $e->actor_id,
                'ip_address' => $e->ip_address,
                'metadata' => $e->metadata,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
        ]]);
    }

    // The response is the only time the card number and activation code are available.
    public function store(Request $request, ProfileLookup $profiles): JsonResponse
    {
        $data = $request->validate([
            'chip_uid' => ['required', 'string', 'max:64', function ($attr, $value, $fail) {
                $hex = CardSecrets::normalizeChipUid((string) $value);
                if (strlen($hex) < 8 || strlen($hex) > 32 || strlen($hex) % 2 !== 0) {
                    $fail('The chip UID must be 4–16 bytes of hexadecimal, as read from the card.');
                }
            }],
            'username' => ['nullable', 'string', 'max:31'],
            'replaces_card_id' => ['nullable', 'string', 'size:26'],
        ]);

        $assignee = isset($data['username']) ? $profiles->find($data['username'], $request->user(), ignoreBlocks: true) : null;
        $replaces = isset($data['replaces_card_id']) ? Card::findOrFail(strtolower($data['replaces_card_id'])) : null;

        if ($replaces && ! $assignee && $replaces->holder) {
            $assignee = $replaces->holder;
        }

        $issued = $this->cards->issuePhysical($request->user(), $data['chip_uid'], $assignee, $replaces);

        return response()->json([
            'data' => $this->present($issued['card']->load(['holder.profile', 'issuer.profile'])),
            'secrets' => [
                'card_number' => trim(chunk_split($issued['number'], 4, ' ')),
                'activation_code' => $issued['activation_code'],
                'notice' => 'Print these on the card mailer now. They cannot be shown again.',
            ],
        ], 201);
    }

    public function revoke(Request $request, string $card): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $model = $this->cards->revoke($request->user(), Card::findOrFail($card), $data['reason']);

        return response()->json(['data' => $this->present($model->load(['holder.profile', 'issuer.profile']))]);
    }

    private function present(Card $card): array
    {
        return [
            'id' => $card->id,
            'type' => $card->type->value,
            'status' => $card->status->value,
            'last4' => $card->number_last4,
            'holder' => $card->holder ? [
                'id' => $card->holder->id,
                'username' => $card->holder->profile?->username,
                'display_name' => $card->holder->profile?->display_name,
            ] : null,
            'issued_by' => $card->issuer?->profile?->username,
            'replaces_card_id' => $card->replaces_card_id,
            'activation_expires_at' => $card->activation_expires_at?->toIso8601String(),
            'activated_at' => $card->activated_at?->toIso8601String(),
            'replacement_requested_at' => $card->replacement_requested_at?->toIso8601String(),
            'revoked_at' => $card->revoked_at?->toIso8601String(),
            'created_at' => $card->created_at->toIso8601String(),
        ];
    }
}
