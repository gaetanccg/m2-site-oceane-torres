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
     * Add a photo to the cart
     */
    public function addItem(Cart $cart, string $photoId): CartItem
    {
        $photo = Photo::findOrFail($photoId);

        // Check if photo is purchasable
        if (! $photo->is_purchasable) {
            throw new \Exception('Cette photo n\'est pas disponible à l\'achat.');
        }

        // Check if already in cart
        $existingItem = $cart->items()->where('photo_id', $photoId)->first();
        if ($existingItem) {
            return $existingItem;
        }

        // Get price (custom price or default)
        $price = $photo->effective_price;

        return CartItem::create([
            'cart_id' => $cart->id,
            'photo_id' => $photoId,
            'price' => $price,
        ]);
    }

    /**
     * Remove a photo from the cart
     */
    public function removeItem(Cart $cart, string $photoId): bool
    {
        return $cart->items()->where('photo_id', $photoId)->delete() > 0;
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
            if (! $userCart->hasPhoto($item->photo_id)) {
                CartItem::create([
                    'cart_id' => $userCart->id,
                    'photo_id' => $item->photo_id,
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
        $cart->load('items.photo.gallery');

        $items = $cart->items->map(function ($item) {
            return [
                'id' => $item->id,
                'photo_id' => $item->photo_id,
                'photo' => [
                    'id' => $item->photo->id,
                    'title' => $item->photo->title,
                    'display_url' => $item->photo->display_url,
                    'gallery_title' => $item->photo->gallery?->title,
                ],
                'price' => (float) $item->price,
            ];
        });

        return [
            'id' => $cart->id,
            'items' => $items,
            'items_count' => $items->count(),
            'total' => $items->sum('price'),
            'currency' => config('sumup.photo.currency', 'EUR'),
        ];
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
