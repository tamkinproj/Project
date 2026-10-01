<?php

namespace App\Modules\Card\Http\Resources;

use App\Modules\Card\Models\CardEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CardEvent */
class CardEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
