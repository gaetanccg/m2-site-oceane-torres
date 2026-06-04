<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\GiftCode;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CartService
{
    private const MAX_ITEM_QUANTITY = 50;

    /**
     * Garantit EXACTEMENT UN cart actif par user : merge le cart guest correspondant
     * (matching session_id) et expire les doublons. Sans ça, le user peut avoir 2-3 carts
     * en parallèle et payer un montant ≠ de ce qu'il voit dans l'UI.
     */
    public function getOrCreateCart(?User $user, ?string $sessionId = null): Cart
    {
        if ($user) {
            $userCart = Cart::active()
                ->forUser($user->id)
                ->with('items.photo')
                ->latest('updated_at')
                ->first();

            $guestCart = null;
            if ($sessionId) {
                $guestCart = Cart::active()
                    ->forSession($sessionId)
                    ->whereNull('user_id')
                    ->with('items')
                    ->first();
            }

            if ($userCart && $guestCart) {
                $this->mergeGuestCart($guestCart, $user);
                $userCart->refresh()->load('items.photo');
            } elseif (! $userCart && $guestCart) {
                $guestCart->update(['user_id' => $user->id, 'session_id' => null]);
                $userCart = $guestCart->load('items.photo');
            } elseif (! $userCart) {
                $userCart = Cart::create([
                    'user_id' => $user->id,
                    'status' => 'active',
                    'expires_at' => now()->addDays(7),
                ]);
            }

            Cart::active()
                ->forUser($user->id)
                ->where('id', '!=', $userCart->id)
                ->update(['status' => 'expired']);

            return $userCart;
        }

        if ($sessionId) {
            $cart = Cart::active()
                ->forSession($sessionId)
                ->whereNull('user_id')
                ->with('items.photo')
                ->first();

            if ($cart) {
                return $cart;
            }
        }

        $newSessionId = $sessionId ?? Str::uuid()->toString();

        return Cart::create([
            'session_id' => $newSessionId,
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function addItem(Cart $cart, string $photoId, string $productType = 'digital', int $quantity = 1): CartItem
    {
        $photo = Photo::findOrFail($photoId);

        if (! array_key_exists($productType, CartItem::PRODUCT_TYPES)) {
            $productType = 'digital';
        }

        if (! $photo->is_purchasable) {
            throw new BusinessException('Cette photo n\'est pas disponible à l\'achat.', 400);
        }

        $gallery = $photo->gallery;
        $gallery->load('galleryProductTypes', 'schoolSession:id,closed_at');

        if ($gallery->schoolSession?->isClosed()) {
            throw new BusinessException('Cette galerie est cloturée, les commandes ne sont plus possibles.', 403);
        }

        $price = $gallery->getPriceForProductType($productType);

        if ($price === null) {
            throw new BusinessException('Ce type de produit n\'est pas disponible pour cette galerie.', 400);
        }

        $quantity = max(1, min(self::MAX_ITEM_QUANTITY, $quantity));

        $existing = $cart->items()
            ->where('photo_id', $photoId)
            ->where('product_type', $productType)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);
            $existing->update(['price' => $price]);
            $cart->unsetRelation('items');

            return $existing->fresh();
        }

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'photo_id' => $photoId,
            'product_type' => $productType,
            'quantity' => $quantity,
            'price' => $price,
        ]);

        $cart->unsetRelation('items');

        return $item->fresh();
    }

    /** Si quantity <= 0 l'item est supprimé. */
    public function setItemQuantity(CartItem $item, int $quantity): ?CartItem
    {
        $quantity = min(self::MAX_ITEM_QUANTITY, $quantity);

        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh();
    }

    public function updateItemType(Cart $cart, string $itemId, string $productType): CartItem
    {
        if (! array_key_exists($productType, CartItem::PRODUCT_TYPES)) {
            throw new BusinessException('Type de produit invalide.', 400);
        }

        $item = $cart->items()->with('photo.gallery.galleryProductTypes')->where('id', $itemId)->firstOrFail();

        $gallery = $item->photo->gallery;
        $price = $gallery->getPriceForProductType($productType);

        if ($price === null) {
            throw new BusinessException('Ce type de produit n\'est pas disponible pour cette galerie.', 400);
        }

        // Si un autre item a déjà cette photo + ce product_type → on fusionne.
        $existingItem = $cart->items()
            ->where('photo_id', $item->photo_id)
            ->where('product_type', $productType)
            ->where('id', '!=', $itemId)
            ->first();

        if ($existingItem) {
            $item->delete();

            return $existingItem;
        }

        $item->update([
            'product_type' => $productType,
            'price' => $price,
        ]);

        $cart->unsetRelation('items');

        return $item->fresh();
    }

    public function removeItem(Cart $cart, string $itemId): bool
    {
        $deleted = $cart->items()->where('id', $itemId)->delete() > 0;
        if ($deleted) {
            $cart->unsetRelation('items');
        }

        return $deleted;
    }

    public function removePhoto(Cart $cart, string $photoId): bool
    {
        $deleted = $cart->items()->where('photo_id', $photoId)->delete() > 0;
        if ($deleted) {
            $cart->unsetRelation('items');
        }

        return $deleted;
    }

    public function clearCart(Cart $cart): bool
    {
        return $cart->items()->delete() > 0;
    }

    public function mergeGuestCart(Cart $guestCart, User $user): Cart
    {
        $userCart = $this->getOrCreateCart($user);

        // Si la même photo+type existe côté user, on ADDITIONNE les quantités
        // (sinon la quantité du panier invité serait silencieusement perdue au login).
        foreach ($guestCart->items as $item) {
            $existing = $userCart->items()
                ->where('photo_id', $item->photo_id)
                ->where('product_type', $item->product_type ?? 'digital')
                ->first();

            $guestQuantity = (int) ($item->quantity ?? 1);

            if ($existing) {
                $existing->update([
                    'quantity' => (int) $existing->quantity + $guestQuantity,
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $userCart->id,
                    'photo_id' => $item->photo_id,
                    'product_type' => $item->product_type ?? 'digital',
                    'price' => $item->price,
                    'quantity' => $guestQuantity,
                ]);
            }
        }

        // Reporte le code promo appliqué au panier invité s'il n'y en a pas déjà un côté user
        // (sinon le code saisi avant login serait silencieusement perdu).
        if ($guestCart->gift_code_id && ! $userCart->gift_code_id) {
            $userCart->update(['gift_code_id' => $guestCart->gift_code_id]);
        }

        $guestCart->markAsExpired();

        // Recalcule les prix de palier après merge (quantités cumulées ont pu changer).
        $userCart->refresh()->load('items.photo.gallery.galleryProductTypes.packTiers');
        $this->recalculatePackPrices($userCart);

        return $userCart->load('items.photo');
    }

    public function setGuestEmail(Cart $cart, string $email): Cart
    {
        $cart->update(['guest_email' => $email]);

        return $cart;
    }

    public function getCartSummary(Cart $cart): array
    {
        $cart->load('items.photo.gallery.galleryProductTypes.packTiers');
        $this->recalculatePackPrices($cart);

        $groups = $this->buildPackGroups($cart);

        $itemGroupCount = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $itemGroupCount[$item->id] = $group['count'];
            }
        }

        $totalSavings = 0;
        foreach ($groups as $group) {
            $first = $group['items']->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $group['count'];

            $gpt = $gallery->galleryProductTypes->firstWhere('product_type', $productType);
            if (! $gpt || $gpt->packTiers->isEmpty()) {
                continue;
            }

            $basePrice = $gpt->effective_price;
            $currentPrice = (float) $first->price;
            if ($currentPrice < $basePrice) {
                $totalSavings += ($basePrice - $currentPrice) * $quantity;
            }
        }

        $items = $cart->items->map(function ($item) use ($itemGroupCount) {
            $gallery = $item->photo->gallery;
            $availableTypes = $gallery ? $gallery->getAvailableProductTypes() : CartItem::PRODUCT_TYPES;

            $packQuantity = $itemGroupCount[$item->id] ?? (int) ($item->quantity ?? 1);
            $itemQuantity = (int) ($item->quantity ?? 1);

            $gpt = $gallery?->galleryProductTypes->firstWhere('product_type', $item->product_type);
            $basePrice = $gpt ? $gpt->effective_price : (float) $item->price;
            $hasPackDiscount = (float) $item->price < $basePrice;

            return [
                'id' => $item->id,
                'photo_id' => $item->photo_id,
                'photo' => [
                    'id' => $item->photo->id,
                    'title' => $item->photo->title,
                    'display_url' => $item->photo->display_url,
                    'thumbnail_url' => $item->photo->thumbnail_url,
                    'gallery_title' => $gallery?->title,
                    'gallery_id' => $gallery?->id,
                ],
                'product_type' => $item->product_type ?? 'digital',
                'product_type_label' => CartItem::getLabelForType($item->product_type ?? 'digital'),
                'is_print' => $item->isPrint(),
                'price' => (float) $item->price,
                'quantity' => $itemQuantity,
                'line_total' => (float) $item->price * $itemQuantity,
                'base_price' => $basePrice,
                'has_pack_discount' => $hasPackDiscount,
                'pack_quantity' => $hasPackDiscount ? $packQuantity : null,
                'available_product_types' => $availableTypes,
            ];
        });

        $subtotal = (float) $items->sum('line_total');
        $hasPrints = $items->contains('is_print', true);
        $requiresShipping = $cart->items->contains(function ($cartItem) {
            $gallery = $cartItem->photo?->gallery;
            $productType = $cartItem->product_type ?? 'digital';

            return $gallery
                ? $gallery->getRequiresShippingForProductType($productType)
                : CartItem::requiresShipping($productType);
        });
        $shippingFee = $requiresShipping ? (float) config('shop.shipping_fee_print', 0) : 0.0;

        [$discount, $giftCode] = $this->resolveGiftCode($cart, $subtotal);

        return [
            'id' => $cart->id,
            'items' => $items,
            'items_count' => $items->sum('quantity'),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'gift_code' => $giftCode,
            'shipping_fee' => $shippingFee,
            'total' => ($subtotal - $discount) + $shippingFee,
            'has_prints' => $hasPrints,
            'requires_shipping' => $requiresShipping,
            'has_pack_pricing' => $totalSavings > 0,
            'pack_savings' => $totalSavings,
            'currency' => 'EUR',
            'product_types' => CartItem::PRODUCT_TYPES,
        ];
    }

    /**
     * Résout le code promo appliqué au panier pour le sous-total courant.
     * Auto-nettoyage : un code devenu invalide est retiré silencieusement.
     *
     * @return array{0: float, 1: array{code: string, type: string, value: float}|null}
     */
    private function resolveGiftCode(Cart $cart, float $subtotal): array
    {
        if (! $cart->gift_code_id) {
            return [0.0, null];
        }

        $code = GiftCode::find($cart->gift_code_id);

        if ($code && app(GiftCodeService::class)->preview($code, $subtotal)['valid']) {
            return [
                $code->effectiveDiscount($subtotal),
                [
                    'code' => $code->code,
                    'type' => $code->type,            // 'fixed' | 'percent'
                    'value' => (float) $code->value,  // euros ou %
                ],
            ];
        }

        // Code invalide/expiré/épuisé → on le retire du panier.
        $cart->update(['gift_code_id' => null]);

        return [0.0, null];
    }

    /**
     * Groupe les items pour le pricing cumulatif cross-galerie : même offer signature
     * (product_type + prix + tiers) = même groupe, même si galeries différentes.
     *
     * @return Collection<string, array{items: Collection, count: int, gpt: \App\Models\GalleryProductType|null}>
     */
    public function buildPackGroups(Cart $cart): Collection
    {
        $cart->loadMissing('items.photo.gallery.galleryProductTypes.packTiers');

        $groups = [];

        foreach ($cart->items as $item) {
            $gallery = $item->photo->gallery;
            $productType = $item->product_type;

            $gpt = $gallery?->galleryProductTypes->firstWhere('product_type', $productType);
            $signature = $gpt?->offerSignature();

            $key = $signature !== null
                ? 'sig:'.$signature
                : 'gal:'.($gallery?->id ?? '').'|'.$productType;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'items' => collect(),
                    'count' => 0,
                    'gpt' => $gpt,
                ];
            }

            $groups[$key]['items']->push($item);
            $groups[$key]['count'] += (int) ($item->quantity ?? 1);
        }

        return collect($groups);
    }

    public function recalculatePackPrices(Cart $cart): void
    {
        $cart->loadMissing('items.photo.gallery.galleryProductTypes.packTiers');
        $groups = $this->buildPackGroups($cart);

        foreach ($groups as $group) {
            $first = $group['items']->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $group['count'];

            $unitPrice = $gallery?->resolvePackPrice($productType, $quantity)
                ?? $gallery?->getPriceForProductType($productType)
                ?? CartItem::getPriceForType($productType);

            foreach ($group['items'] as $item) {
                if ((float) $item->price !== $unitPrice) {
                    $item->update(['price' => $unitPrice]);
                }
            }
        }
    }

    public function cleanupExpiredCarts(): int
    {
        return Cart::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
