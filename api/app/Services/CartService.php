<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Get or create a cart for the given user/session
     */
    public function getOrCreateCart(?User $user, ?string $sessionId = null): Cart
    {
        // If user is logged in, try to find their cart
        if ($user) {
            $cart = Cart::active()
                ->forUser($user->id)
                ->with('items.photo')
                ->first();

            if ($cart) {
                return $cart;
            }

            // If there's a session cart, merge it with user
            if ($sessionId) {
                $sessionCart = Cart::active()
                    ->forSession($sessionId)
                    ->whereNull('user_id')
                    ->first();

                if ($sessionCart) {
                    $sessionCart->update(['user_id' => $user->id, 'session_id' => null]);

                    return $sessionCart->load('items.photo');
                }
            }

            // Create new cart for user
            return Cart::create([
                'user_id' => $user->id,
                'status' => 'active',
                'expires_at' => now()->addDays(7),
            ]);
        }

        // Guest cart by session
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

        // Create new guest cart
        $newSessionId = $sessionId ?? Str::uuid()->toString();

        return Cart::create([
            'session_id' => $newSessionId,
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Add a photo to the cart with a specific product type
     */
    public function addItem(Cart $cart, string $photoId, string $productType = 'digital'): CartItem
    {
        $photo = Photo::findOrFail($photoId);

        // Validate product type
        if (! array_key_exists($productType, CartItem::PRODUCT_TYPES)) {
            $productType = 'digital';
        }

        // Check if photo is purchasable
        if (! $photo->is_purchasable) {
            throw new \Exception('Cette photo n\'est pas disponible à l\'achat.');
        }

        // Resolve price from gallery product config (with fallback to defaults)
        $gallery = $photo->gallery;
        $gallery->load('galleryProductTypes');
        $price = $gallery->getPriceForProductType($productType);

        if ($price === null) {
            throw new \Exception('Ce type de produit n\'est pas disponible pour cette galerie.');
        }

        // Check if same photo with same product type already in cart
        $existingItem = $cart->items()
            ->where('photo_id', $photoId)
            ->where('product_type', $productType)
            ->first();

        if ($existingItem) {
            return $existingItem;
        }

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'photo_id' => $photoId,
            'product_type' => $productType,
            'price' => $price,
        ]);

        $cart->unsetRelation('items');
        $this->recalculatePackPrices($cart);

        return $item->fresh();
    }

    /**
     * Update product type for a cart item
     */
    public function updateItemType(Cart $cart, string $itemId, string $productType): CartItem
    {
        // Validate product type
        if (! array_key_exists($productType, CartItem::PRODUCT_TYPES)) {
            throw new \Exception('Type de produit invalide.');
        }

        $item = $cart->items()->with('photo.gallery.galleryProductTypes')->where('id', $itemId)->firstOrFail();

        // Resolve price from gallery product config
        $gallery = $item->photo->gallery;
        $price = $gallery->getPriceForProductType($productType);

        if ($price === null) {
            throw new \Exception('Ce type de produit n\'est pas disponible pour cette galerie.');
        }

        // Check if another item with same photo and product type exists
        $existingItem = $cart->items()
            ->where('photo_id', $item->photo_id)
            ->where('product_type', $productType)
            ->where('id', '!=', $itemId)
            ->first();

        if ($existingItem) {
            // Delete current item and return existing
            $item->delete();

            return $existingItem;
        }

        // Update product type and price
        $item->update([
            'product_type' => $productType,
            'price' => $price,
        ]);

        $cart->unsetRelation('items');
        $this->recalculatePackPrices($cart);

        return $item->fresh();
    }

    /**
     * Remove an item from the cart by item ID
     */
    public function removeItem(Cart $cart, string $itemId): bool
    {
        $deleted = $cart->items()->where('id', $itemId)->delete() > 0;
        if ($deleted) {
            $cart->unsetRelation('items');
            $this->recalculatePackPrices($cart);
        }

        return $deleted;
    }

    /**
     * Remove a photo from the cart (all product types)
     */
    public function removePhoto(Cart $cart, string $photoId): bool
    {
        $deleted = $cart->items()->where('photo_id', $photoId)->delete() > 0;
        if ($deleted) {
            $cart->unsetRelation('items');
            $this->recalculatePackPrices($cart);
        }

        return $deleted;
    }

    /**
     * Clear all items from the cart
     */
    public function clearCart(Cart $cart): bool
    {
        return $cart->items()->delete() > 0;
    }

    /**
     * Merge a guest cart into a user cart
     */
    public function mergeGuestCart(Cart $guestCart, User $user): Cart
    {
        $userCart = $this->getOrCreateCart($user);

        // Move items from guest cart to user cart
        foreach ($guestCart->items as $item) {
            // Check if same photo with same product type exists
            $exists = $userCart->items()
                ->where('photo_id', $item->photo_id)
                ->where('product_type', $item->product_type ?? 'digital')
                ->exists();

            if (! $exists) {
                CartItem::create([
                    'cart_id' => $userCart->id,
                    'photo_id' => $item->photo_id,
                    'product_type' => $item->product_type ?? 'digital',
                    'price' => $item->price,
                ]);
            }
        }

        // Mark guest cart as expired
        $guestCart->markAsExpired();

        return $userCart->load('items.photo');
    }

    /**
     * Update guest email on cart
     */
    public function setGuestEmail(Cart $cart, string $email): Cart
    {
        $cart->update(['guest_email' => $email]);

        return $cart;
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(Cart $cart): array
    {
        $this->recalculatePackPrices($cart);
        $cart->load('items.photo.gallery.galleryProductTypes.packTiers');

        // Build pack info: group items by gallery+type to compute savings
        $groups = $cart->items->groupBy(fn ($item) => $item->photo->gallery_id.'|'.$item->product_type
        );

        $packInfo = [];
        $totalSavings = 0;
        foreach ($groups as $key => $groupItems) {
            $first = $groupItems->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $groupItems->count();

            $gpt = $gallery->galleryProductTypes->firstWhere('product_type', $productType);
            if (! $gpt || $gpt->packTiers->isEmpty()) {
                continue;
            }

            $basePrice = $gpt->effective_price;
            $currentPrice = (float) $first->price;
            if ($currentPrice < $basePrice) {
                $savings = ($basePrice - $currentPrice) * $quantity;
                $totalSavings += $savings;
                $packInfo[$key] = [
                    'gallery_id' => $gallery->id,
                    'product_type' => $productType,
                    'quantity' => $quantity,
                    'base_price' => $basePrice,
                    'unit_price' => $currentPrice,
                    'savings' => $savings,
                ];
            }
        }

        $items = $cart->items->map(function ($item) use ($groups) {
            $gallery = $item->photo->gallery;
            $availableTypes = $gallery ? $gallery->getAvailableProductTypes() : CartItem::PRODUCT_TYPES;

            $groupKey = $item->photo->gallery_id.'|'.$item->product_type;
            $groupItems = $groups[$groupKey] ?? collect();
            $quantity = $groupItems->count();

            // Determine base price for this product type
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
                'base_price' => $basePrice,
                'has_pack_discount' => $hasPackDiscount,
                'pack_quantity' => $hasPackDiscount ? $quantity : null,
                'available_product_types' => $availableTypes,
            ];
        });

        return [
            'id' => $cart->id,
            'items' => $items,
            'items_count' => $items->count(),
            'total' => $items->sum('price'),
            'has_prints' => $items->contains('is_print', true),
            'has_pack_pricing' => $totalSavings > 0,
            'pack_savings' => $totalSavings,
            'currency' => 'EUR',
            'product_types' => CartItem::PRODUCT_TYPES,
        ];
    }

    /**
     * Recalculate pack prices for all items in the cart.
     * Groups items by gallery+product_type and resolves pack tier pricing.
     */
    public function recalculatePackPrices(Cart $cart): void
    {
        $cart->load('items.photo.gallery.galleryProductTypes.packTiers');

        $groups = $cart->items->groupBy(fn ($item) => $item->photo->gallery_id.'|'.$item->product_type
        );

        foreach ($groups as $items) {
            $first = $items->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $items->count();

            $unitPrice = $gallery->resolvePackPrice($productType, $quantity);
            if ($unitPrice === null) {
                continue;
            }

            foreach ($items as $item) {
                if ((float) $item->price !== $unitPrice) {
                    $item->update(['price' => $unitPrice]);
                }
            }
        }
    }

    /**
     * Clean up expired carts
     */
    public function cleanupExpiredCarts(): int
    {
        return Cart::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
