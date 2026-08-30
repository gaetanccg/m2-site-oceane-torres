<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGiftCardRequest;
use App\Http\Requests\StoreGiftCardRequest;
use App\Models\GiftCard;
use App\Services\AdminStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GiftCard::with('payment');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.strtoupper($search).'%')
                    ->orWhere('recipient_email', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%");
            });
        }

        $this->filterByStatus($query, (string) $request->input('status', ''));

        $giftCards = $this->paginated(
            $this->sorted($query, $request, ['created_at', 'status', 'expires_at', 'amount', 'code']),
            $request
        );

        return response()->json($giftCards);
    }

    /**
     * « Expiré » n'est pas une valeur de `status` : le code ne l'écrit jamais. C'est une
     * date dépassée sur un bon encore actif — la même définition que dans les statistiques,
     * sans quoi le filtre et le compteur donneraient deux nombres différents.
     */
    private function filterByStatus(mixed $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        if ($status === 'expired') {
            $query->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now());

            return;
        }

        if ($status === 'active') {
            $query->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

            return;
        }

        $query->where('status', $status);
    }

    public function stats(AdminStatsService $stats): JsonResponse
    {
        return response()->json($stats->giftCards());
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
