<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\ApplyGiftCodeRequest;
use App\Http\Requests\UpdateCartEmailRequest;
use App\Http\Requests\UpdateCartItemTypeRequest;
use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use App\Services\GiftCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class CartController extends Controller
{
    /** Anti brute-force : tentatives ÉCHOUÉES max par IP (les succès ne comptent pas). */
    private const GIFT_CODE_MAX_FAILURES = 10;

    private const GIFT_CODE_FAILURE_DECAY = 600;

    private CartService $cartService;

    private GiftCodeService $giftCodeService;

    public function __construct(CartService $cartService, GiftCodeService $giftCodeService)
    {
        $this->cartService = $cartService;
        $this->giftCodeService = $giftCodeService;
    }

    /**
     * Sur ces routes publiques, `$request->user()` (guard `web`) ignore le Bearer token
     * alors que le checkout résout via sanctum. Sans cette symétrie, un client connecté
     * manipule un panier session que le checkout fusionne ailleurs → totaux divergents.
     */
    private function resolveUser(Request $request): ?User
    {
        return $request->user() ?? Auth::guard('sanctum')->user();
    }

    /**
     * Get current cart
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
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
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;
        $productType = $validated['product_type'] ?? 'digital';
        $quantity = $validated['quantity'] ?? 1;

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);
            $this->cartService->addItem($cart, $validated['photo_id'], $productType, $quantity);
            $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));

            return response()->json([
                'success' => true,
                'message' => 'Photo ajoutée au panier.',
                'cart' => $summary,
                'session_id' => $cart->session_id,
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Cart addItem failed', [
                'photo_id' => $validated['photo_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter.",
            ], 500);
        }
    }

    /**
     * Update item product type
     */
    public function updateItemType(UpdateCartItemTypeRequest $request, string $itemId): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);
            $this->cartService->updateItemType($cart, $itemId, $validated['product_type']);
            $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));

            return response()->json([
                'success' => true,
                'message' => 'Type de produit mis à jour.',
                'cart' => $summary,
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Cart updateItemType failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter.",
            ], 500);
        }
    }

    /**
     * Update the quantity of a cart item. If quantity reaches 0, the item is removed.
     */
    public function updateItemQuantity(Request $request, string $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $item = $cart->items()->where('id', $itemId)->first();

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouve dans le panier.',
            ], 404);
        }

        $this->cartService->setItemQuantity($item, (int) $validated['quantity']);

        $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));

        return response()->json([
            'success' => true,
            'cart' => $summary,
        ]);
    }

    /**
     * Remove an item from cart by item ID
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $removed = $this->cartService->removeItem($cart, $itemId);

        if (! $removed) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier.',
            ], 404);
        }

        $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));

        return response()->json([
            'success' => true,
            'message' => 'Article retiré du panier.',
            'cart' => $summary,
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
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
                'currency' => 'EUR',
            ],
        ]);
    }

    /**
     * Update guest email on cart
     */
    public function updateEmail(UpdateCartEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $validated['session_id'] ?? null;

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $this->cartService->setGuestEmail($cart, $validated['email']);

        return response()->json([
            'success' => true,
            'message' => 'Email mis a jour.',
        ]);
    }

    public function applyGiftCode(ApplyGiftCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        // Anti brute-force, 2e couche après le throttle:gift-code : seuls les échecs comptent.
        $throttleKey = 'gift-code-failures:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::GIFT_CODE_MAX_FAILURES)) {
            Log::warning('Gift code brute-force lockout', [
                'ip' => $request->ip(),
            ]);

            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            return response()->json([
                'success' => false,
                'message' => "Trop de tentatives. Veuillez réessayer dans {$minutes} minute(s).",
            ], 429);
        }

        try {
            $cart = $this->cartService->getOrCreateCart($user, $sessionId);

            $code = $this->giftCodeService->resolve($validated['code']);
            if (! $code) {
                RateLimiter::hit($throttleKey, self::GIFT_CODE_FAILURE_DECAY);

                return response()->json([
                    'success' => false,
                    'message' => "Ce code promo n'existe pas.",
                ], 404);
            }

            $summary = $this->cartService->getCartSummary($cart->fresh(['items.photo']));
            $preview = $this->giftCodeService->preview($code, (float) $summary['subtotal']);

            if (! $preview['valid']) {
                RateLimiter::hit($throttleKey, self::GIFT_CODE_FAILURE_DECAY);

                return response()->json([
                    'success' => false,
                    'message' => $preview['reason'],
                ], 422);
            }

            $cart->update(['gift_code_id' => $code->id]);
            RateLimiter::clear($throttleKey);

            return response()->json([
                'success' => true,
                'message' => 'Code promo appliqué.',
                'cart' => $this->cartService->getCartSummary($cart->fresh(['items.photo'])),
            ]);
        } catch (BusinessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        }
    }

    public function removeGiftCode(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        $sessionId = $request->header('X-Cart-Session') ?? $request->input('session_id');

        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $cart->update(['gift_code_id' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Code promo retiré.',
            'cart' => $this->cartService->getCartSummary($cart->fresh(['items.photo'])),
        ]);
    }

    /**
     * Merge guest cart with authenticated user cart (called after login)
     */
    public function merge(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
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

        $guestCart = Cart::active()
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
