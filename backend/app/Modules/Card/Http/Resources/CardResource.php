<?php

namespace App\Modules\Card\Http\Resources;

use App\Modules\Card\Enums\CardStatus;
use App\Modules\Card\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Card */
// The holder's own view of a card. Contains no secrets: only the last four digits.
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $physical = $this->isPhysical();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'last4' => $this->number_last4,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'frozen_at' => $this->frozen_at?->toIso8601String(),
            'lost_reported_at' => $this->lost_reported_at?->toIso8601String(),
            'replacement_requested_at' => $this->replacement_requested_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'actions' => [
                'freeze' => $this->status === CardStatus::Active,
                'unfreeze' => $this->status === CardStatus::Frozen,
                'report_lost' => $physical && in_array($this->status, [CardStatus::Active, CardStatus::Frozen], true),
                'request_replacement' => $physical && ! $this->replacement_requested_at
                    && in_array($this->status, [CardStatus::Active, CardStatus::Frozen, CardStatus::Lost], true),
            ],
        ];
    }
}
