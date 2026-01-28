<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function index(): JsonResponse
    {
        $giftCards = GiftCard::with('payment')
            ->latest()
            ->paginate(20);

        return response()->json($giftCards);
    }

    public function show(GiftCard $giftCard): JsonResponse
    {
        return response()->json([
            'gift_card' => $giftCard->load('payment'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:500'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

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

    public function update(Request $request, GiftCard $giftCard): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:active,used,expired,cancelled'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $giftCard->update($validated);

        return response()->json([
            'gift_card' => $giftCard->fresh(),
            'message' => 'Bon cadeau mis à jour.',
        ]);
    }

    public function validate(string $code): JsonResponse
    {
        $giftCard = GiftCard::where('code', $code)->first();

        if (! $giftCard) {
            return response()->json([
                'valid' => false,
                'message' => 'Code invalide.',
            ], 404);
        }

        if (! $giftCard->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'Ce bon cadeau a expiré ou a déjà été utilisé.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'remaining_amount' => $giftCard->remaining_amount,
            'expires_at' => $giftCard->expires_at,
        ]);
    }
}
