<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get current cart
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $summary = $this->cartService->getCartSummary($cart);

        return response()->json([
            'success' => true,
            'cart' => $summary,
            'session_id' => $cart->session_id,
        ]);
    }

    /**
     * Add a photo to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo_id' => ['required', 'uuid', 'exists:photos,id'],
            'session_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);
            $this->cartService->addItem($cart, $validated['photo_id']);
            $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));

            return response()->json([
                'success' => true,
                'message' => 'Photo ajoutee au panier.',
                'cart' => $summary,
                'session_id' => $cart->session_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove a photo from cart
     */
    public function removeItem(Request $request, string $photoId): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $removed = $this->cartService->removeItem($cart, $photoId);

        if (! $removed) {
            return response()->json([
                'success' => false,
                'message' => 'Photo non trouvee dans le panier.',
            ], 404);
        }

        $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));

        return response()->json([
            'success' => true,
            'message' => 'Photo retiree du panier.',
            'cart' => $summary,
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $this->cartService->clearCart($cart);

        return response()->json([
            'success' => true,
            'message' => 'Panier vide.',
            'cart' => [
                'id' => $cart->id,
                'items' => [],
                'items_count' => 0,
                'total' => 0,
                'currency' => config('sumup.photo.currency', 'EUR'),
            ],
        ]);
    }

    /**
     * Update guest email on cart
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'session_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $this->cartService->setGuestEmail($cart, $validated['email']);

        return response()->json([
            'success' => true,
            'message' => 'Email mis a jour.',
        ]);
    }

    /**
     * Merge guest cart with authenticated user cart (called after login)
     */
    public function merge(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');
        if (! $sessionId) {
            return response()->json([
                'success' => true,
                'message' => 'Aucun panier invite a fusionner.',
            ]);
        }

        $guestCart = \App\Models\Cart::active()
            ->forSession($sessionId)
            ->whereNull('user_id')
            ->first();

        if (! $guestCart) {
            return response()->json([
                'success' => true,
                'message' => 'Aucun panier invite a fusionner.',
            ]);
        }

        $mergedCart = $this->cartService->mergeGuestCart($guestCart, $user);
        $summary = $this->cartService->getCartSummary($mergedCart);

        return response()->json([
            'success' => true,
            'message' => 'Panier fusionne.',
            'cart' => $summary,
        ]);
    }
}
