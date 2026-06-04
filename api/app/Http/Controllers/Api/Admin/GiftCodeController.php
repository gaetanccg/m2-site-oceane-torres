<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGiftCodeRequest;
use App\Http\Requests\Admin\UpdateGiftCodeRequest;
use App\Models\GiftCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCodeController extends Controller
{
    /**
     * Liste paginée des codes promo avec recherche et filtre de statut.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GiftCode::query()
            ->withCount([
                'orders as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'orders as paid_count' => fn ($q) => $q->where('status', 'paid'),
            ]);

        if ($search = $request->input('search')) {
            $query->where('code', 'like', '%'.strtoupper($search).'%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $codes = $query->latest()->paginate($request->get('per_page', 20));

        $codes->getCollection()->transform(fn (GiftCode $code) => $this->formatGiftCode($code));

        return response()->json($codes);
    }

    /**
     * Détail d'un code + commandes l'ayant consommé (tracking « utilisé »).
     */
    public function show(GiftCode $giftCode): JsonResponse
    {
        $orders = $giftCode->orders()
            ->with('user:id,first_name,last_name,email')
            ->latest()
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => (float) $order->total,
                'discount_amount' => (float) $order->discount_amount,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'created_at' => $order->created_at->toIso8601String(),
            ]);

        return response()->json([
            'gift_code' => array_merge($this->formatGiftCode($giftCode), [
                'orders' => $orders,
            ]),
        ]);
    }

    public function store(StoreGiftCodeRequest $request): JsonResponse
    {
        $giftCode = GiftCode::create($request->validated());

        return response()->json([
            'gift_code' => $this->formatGiftCode($giftCode),
            'message' => 'Code promo créé avec succès.',
        ], 201);
    }

    public function update(UpdateGiftCodeRequest $request, GiftCode $giftCode): JsonResponse
    {
        $giftCode->update($request->validated());

        return response()->json([
            'gift_code' => $this->formatGiftCode($giftCode->fresh()),
            'message' => 'Code promo mis à jour.',
        ]);
    }

    /**
     * Activer / désactiver un code (interrupteur).
     */
    public function toggle(GiftCode $giftCode): JsonResponse
    {
        $giftCode->update(['is_active' => ! $giftCode->is_active]);

        return response()->json([
            'gift_code' => $this->formatGiftCode($giftCode->fresh()),
            'message' => $giftCode->is_active ? 'Code activé.' : 'Code désactivé.',
        ]);
    }

    /**
     * Suppression en deux temps : on ne peut supprimer qu'un code désactivé.
     * Les commandes passées restent intactes (FK nullOnDelete + snapshot du code).
     */
    public function destroy(GiftCode $giftCode): JsonResponse
    {
        if ($giftCode->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Désactivez le code avant de le supprimer.',
            ], 422);
        }

        $giftCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Code promo supprimé.',
        ]);
    }

    /**
     * Génère un code unique non persisté (pour pré-remplir le formulaire de création).
     */
    public function generateCode(): JsonResponse
    {
        return response()->json([
            'code' => GiftCode::generateUniqueCode(),
        ]);
    }

    /**
     * Format API d'un code avec statut calculé et compteurs d'utilisation.
     */
    private function formatGiftCode(GiftCode $code): array
    {
        $pending = $code->pending_count ?? $code->pendingCount();
        $paid = $code->paid_count ?? $code->paidCount();

        return [
            'id' => $code->id,
            'code' => $code->code,
            'type' => $code->type,
            'value' => (float) $code->value,
            'max_discount_amount' => $code->max_discount_amount !== null ? (float) $code->max_discount_amount : null,
            'valid_from' => $code->valid_from?->toIso8601String(),
            'valid_until' => $code->valid_until?->toIso8601String(),
            'max_uses' => $code->max_uses,
            'is_active' => $code->is_active,
            'note' => $code->note,
            'pending_count' => (int) $pending,
            'paid_count' => (int) $paid,
            'status' => $this->computeStatus($code, (int) $paid),
            'created_at' => $code->created_at->toIso8601String(),
        ];
    }

    /** Le statut « épuisé » se base sur les commandes PAYÉES uniquement. */
    private function computeStatus(GiftCode $code, int $paid): string
    {
        if (! $code->is_active) {
            return 'inactive';
        }

        if ($code->valid_from && $code->valid_from->isFuture()) {
            return 'scheduled';
        }

        if ($code->valid_until && $code->valid_until->isPast()) {
            return 'expired';
        }

        if ($code->max_uses !== null && $paid >= $code->max_uses) {
            return 'used_up';
        }

        return 'active';
    }
}
