<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGiftCardRequest;
use App\Http\Requests\StoreGiftCardRequest;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;

class GiftCardController extends Controller
{
    public function index(): JsonResponse
    {
        $giftCards = $this->paginated(
            $this->sorted(GiftCard::with('payment'), request(), ['created_at', 'status', 'expires_at']),
            request()
        );

        return response()->json($giftCards);
    }

    public function store(StoreGiftCardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $giftCard = GiftCard::create([
            ...$validated,
            'remaining_amount' => $validated['amount'],
            'expires_at' => now()->addYear(),
        ]);

        // TODO: Generate PDF and send email

        return response()->json([
            'gift_card' => $giftCard,
            'message' => 'Bon cadeau créé avec succès.',
        ], 201);
    }

    public function update(UpdateGiftCardRequest $request, GiftCard $giftCard): JsonResponse
    {
        $validated = $request->validated();

        $giftCard->update($validated);

        return response()->json([
            'gift_card' => $giftCard->fresh(),
            'message' => 'Bon cadeau mis à jour.',
        ]);
    }
}
