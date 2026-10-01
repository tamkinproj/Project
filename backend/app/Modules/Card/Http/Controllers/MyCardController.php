<?php

namespace App\Modules\Card\Http\Controllers;

use App\Modules\Card\Http\Resources\CardEventResource;
use App\Modules\Card\Http\Resources\CardResource;
use App\Modules\Card\Models\Card;
use App\Modules\Card\Services\CardService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyCardController
{
    public function __construct(private readonly CardService $cards) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $cards = $request->user()->cards()
            ->orderByRaw("CASE WHEN type = 'virtual' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return CardResource::collection($cards);
    }

    public function link(Request $request): CardResource
    {
        $data = $request->validate([
            'activation_code' => ['required', 'string', 'max:20'],
            'last4' => ['required', 'string', 'digits:4'],
        ]);

        return new CardResource($this->cards->activate($request->user(), $data['activation_code'], $data['last4']));
    }

    public function freeze(Request $request, string $card): CardResource
    {
        return new CardResource($this->cards->freeze($request->user(), $this->own($request, $card)));
    }

    public function unfreeze(Request $request, string $card): CardResource
    {
        return new CardResource($this->cards->unfreeze($request->user(), $this->own($request, $card)));
    }

    public function reportLost(Request $request, string $card): CardResource
    {
        return new CardResource($this->cards->reportLost($request->user(), $this->own($request, $card)));
    }

    public function requestReplacement(Request $request, string $card): CardResource
    {
        return new CardResource($this->cards->requestReplacement($request->user(), $this->own($request, $card)));
    }

    public function events(Request $request, string $card): AnonymousResourceCollection
    {
        return CardEventResource::collection($this->own($request, $card)->events()->limit(50)->get());
    }

    // Another member's card ID resolves to 404, never 403.
    private function own(Request $request, string $id): Card
    {
        return $request->user()->cards()->findOrFail($id);
    }
}
