<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * addItem ecretait l'INCREMENT et non le total : dix appels de 50 donnaient 500
 * exemplaires, soit une commande de plusieurs milliers d'euros et 500 tirages a produire.
 * La fusion invite -> compte additionnait sans plafond non plus.
 */
class QuantityCapTest extends TestCase
{
    use RefreshDatabase;

    private const PLAFOND = 50;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function photo(): Photo
    {
        $gallery = Gallery::factory()->create();

        return Photo::factory()->create(['gallery_id' => $gallery->id, 'is_purchasable' => true]);
    }

    private function service(): CartService
    {
        return app(CartService::class);
    }

    public function test_un_seul_ajout_est_ecrete(): void
    {
        $cart = Cart::factory()->create();
        $item = $this->service()->addItem($cart, $this->photo()->id, 'digital', 9999);

        $this->assertSame(self::PLAFOND, $item->quantity);
    }

    public function test_le_cumul_de_plusieurs_ajouts_est_ecrete(): void
    {
        $cart = Cart::factory()->create();
        $photo = $this->photo();

        for ($i = 0; $i < 10; $i++) {
            $this->service()->addItem($cart, $photo->id, 'digital', self::PLAFOND);
        }

        $this->assertSame(self::PLAFOND, CartItem::sole()->quantity);
    }

    public function test_des_ajouts_unitaires_repetes_restent_sous_le_plafond(): void
    {
        $cart = Cart::factory()->create();
        $photo = $this->photo();

        for ($i = 0; $i < self::PLAFOND + 20; $i++) {
            $this->service()->addItem($cart, $photo->id, 'digital', 1);
        }

        $this->assertSame(self::PLAFOND, CartItem::sole()->quantity);
    }

    public function test_la_fusion_invite_vers_compte_ecrete_la_somme(): void
    {
        $user = User::factory()->create();
        $photo = $this->photo();

        $panierCompte = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $this->service()->addItem($panierCompte, $photo->id, 'digital', 40);

        $panierInvite = Cart::factory()->create(['session_id' => 'session-invitee', 'status' => 'active']);
        $this->service()->addItem($panierInvite, $photo->id, 'digital', 40);

        $this->service()->getOrCreateCart($user, 'session-invitee');

        $this->assertLessThanOrEqual(
            self::PLAFOND,
            CartItem::where('cart_id', $panierCompte->id)->sum('quantity')
        );
    }

    public function test_la_quantite_reste_au_moins_a_un(): void
    {
        $cart = Cart::factory()->create();
        $item = $this->service()->addItem($cart, $this->photo()->id, 'digital', 0);

        $this->assertSame(1, $item->quantity);
    }

    public function test_le_prix_est_rafraichi_a_chaque_ajout(): void
    {
        $cart = Cart::factory()->create();
        $photo = $this->photo();

        $premier = $this->service()->addItem($cart, $photo->id, 'digital', 1);
        $second = $this->service()->addItem($cart, $photo->id, 'digital', 1);

        $this->assertSame($premier->price, $second->price);
        $this->assertSame(2, $second->quantity);
    }
}
