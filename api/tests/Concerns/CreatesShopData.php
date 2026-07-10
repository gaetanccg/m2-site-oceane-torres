<?php

namespace Tests\Concerns;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Gallery;
use App\Models\GiftCode;
use App\Models\Photo;

/**
 * Fabriques de haut niveau pour les données boutique (galeries, photos, paniers)
 * partagées par les tests paiement / téléchargement / upload.
 */
trait CreatesShopData
{
    /**
     * Crée un panier invité actif (identifié par session_id) contenant `$count`
     * articles numériques (digital, 13 € pièce).
     */
    protected function makeGuestCartWithDigitalItems(int $count = 1, string $sessionId = 'test-session'): Cart
    {
        $cart = Cart::factory()->create(['session_id' => $sessionId]);
        $gallery = Gallery::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
            CartItem::factory()->digital()->create([
                'cart_id' => $cart->id,
                'photo_id' => $photo->id,
            ]);
        }

        return $cart->load('items.photo');
    }

    /**
     * Crée un panier invité avec un tirage papier (print_10x15) — nécessite une
     * adresse de livraison au checkout.
     */
    protected function makeGuestCartWithPrintItem(string $sessionId = 'test-session'): Cart
    {
        $cart = Cart::factory()->create(['session_id' => $sessionId]);
        $gallery = Gallery::factory()->create();
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);

        CartItem::factory()->print('print_10x15')->create([
            'cart_id' => $cart->id,
            'photo_id' => $photo->id,
        ]);

        return $cart->load('items.photo');
    }

    /**
     * Attache un code cadeau au panier et le retourne.
     */
    protected function attachGiftCode(Cart $cart, GiftCode $code): GiftCode
    {
        $cart->update(['gift_code_id' => $code->id]);

        return $code;
    }
}
