<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Collection;
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
            // Get the most recently updated active cart for this user
            $cart = Cart::active()
                ->forUser($user->id)
                ->with('items.photo')
                ->latest('updated_at')
                ->first();

            // Expire any other active carts for this user (prevent duplicates)
            if ($cart) {
                Cart::active()
                    ->forUser($user->id)
                    ->where('id', '!=', $cart->id)
                    ->update(['status' => 'expired']);

                return $cart;
            }

            // If there's a session cart, claim it for the user
            if ($sessionId) {
                $sessionCart = Cart::active()
                    ->forSession($sessionId)
                    ->whereNull('user_id')
                    ->first();

                if ($sessionCart) {
                    // Expire any stale active carts for this user before claiming
                    Cart::active()->forUser($user->id)->update(['status' => 'expired']);

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
        // Load relations once upfront — recalculatePackPrices and buildPackGroups use loadMissing
        $cart->load('items.photo.gallery.galleryProductTypes.packTiers');
        $this->recalculatePackPrices($cart);

        $groups = $this->buildPackGroups($cart);

        // Build a map: item_id → group count (cumulative quantity)
        $itemGroupCount = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $itemGroupCount[$item->id] = $group['count'];
            }
        }

        // Compute total savings across cumulative groups
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

            $quantity = $itemGroupCount[$item->id] ?? 1;

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
     * Build pack groups for cumulative cross-gallery pricing.
     *
     * Items with the same offer signature (same product_type, base price, and pack tiers)
     * are grouped together even if they belong to different galleries.
     * Items without a signature fall back to per-gallery grouping.
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
            $groups[$key]['count']++;
        }

        return collect($groups);
    }

    /**
     * Recalculate prices for all items in the cart.
     * Always syncs with current gallery prices, then applies pack tier discounts.
     */
    public function recalculatePackPrices(Cart $cart): void
    {
        $cart->loadMissing('items.photo.gallery.galleryProductTypes.packTiers');
        $groups = $this->buildPackGroups($cart);

        foreach ($groups as $group) {
            $first = $group['items']->first();
            $gallery = $first->photo->gallery;
            $productType = $first->product_type;
            $quantity = $group['count'];

            // First try pack price, then fall back to current gallery base price
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
