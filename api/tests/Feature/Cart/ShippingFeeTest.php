<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Photo;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le calcul vivait en trois copies. Ces tests figent la regle avant la factorisation :
 * ils doivent passer a l'identique avant et apres.
 */
class ShippingFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function item(string $productType, ?float $galleryFee = null): CartItem
    {
        $gallery = Gallery::factory()->create(['shipping_fee' => $galleryFee]);
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);

        $item = new CartItem(['product_type' => $productType, 'quantity' => 1, 'price' => 10]);
        $item->setRelation('photo', $photo->setRelation('gallery', $gallery));

        return $item;
    }

    private function service(): PricingService
    {
        return app(PricingService::class);
    }

    public function test_un_panier_numerique_n_a_pas_de_frais_de_port(): void
    {
        $items = [$this->item('digital'), $this->item('digital')];

        $this->assertFalse($this->service()->requiresShipping($items));
        $this->assertSame(0.0, $this->service()->shippingFeeFor($items));
    }

    public function test_un_tirage_declenche_les_frais_de_port(): void
    {
        $items = [$this->item('print_10x15', 5.0)];

        $this->assertTrue($this->service()->requiresShipping($items));
        $this->assertSame(5.0, $this->service()->shippingFeeFor($items));
    }

    /** Un panier mixte ne paie qu'une expedition, au tarif le plus eleve. */
    public function test_sur_un_panier_mixte_le_maximum_s_applique(): void
    {
        $items = [
            $this->item('print_10x15', 5.0),
            $this->item('print_15x20', 9.0),
            $this->item('digital'),
        ];

        $this->assertSame(9.0, $this->service()->shippingFeeFor($items));
    }

    public function test_le_numerique_ne_pese_pas_dans_le_maximum(): void
    {
        $items = [$this->item('digital', 99.0), $this->item('print_10x15', 4.0)];

        $this->assertSame(4.0, $this->service()->shippingFeeFor($items));
    }

    public function test_sans_tarif_de_galerie_le_defaut_de_configuration_s_applique(): void
    {
        config(['shop.shipping_fee_print' => 7.5]);

        $this->assertSame(7.5, $this->service()->shippingFeeFor([$this->item('print_10x15')]));
    }

    public function test_un_panier_vide_ne_coute_rien(): void
    {
        $this->assertSame(0.0, $this->service()->shippingFeeFor([]));
        $this->assertFalse($this->service()->requiresShipping([]));
    }

    /** print_scolaire est remis en main propre : pas d'expedition. */
    public function test_le_tirage_scolaire_est_exempte(): void
    {
        $items = [$this->item('print_scolaire', 12.0)];

        $this->assertFalse($this->service()->requiresShipping($items));
        $this->assertSame(0.0, $this->service()->shippingFeeFor($items));
    }

    /**
     * Le defaut que #45 visait : un ecart entre le port AFFICHE et le port DEBITE. Aucun
     * test ne les comparait pour le meme panier, ce que la factorisation seule ne garantit
     * pas — les deux appelants ne passaient pas le meme ensemble d'articles.
     */
    public function test_le_port_affiche_est_celui_debite(): void
    {
        $galerieChere = Gallery::factory()->create(['shipping_fee' => 9.0]);
        $galerieBonMarche = Gallery::factory()->create(['shipping_fee' => 3.0]);

        $cart = Cart::factory()->create(['session_id' => 'compare']);
        foreach ([$galerieChere, $galerieBonMarche] as $galerie) {
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'photo_id' => Photo::factory()->create(['gallery_id' => $galerie->id])->id,
                'product_type' => 'print_10x15',
                'price' => 10,
            ]);
        }

        $affiche = $this->withHeader('X-Cart-Session', 'compare')
            ->getJson('/api/cart')->assertOk()->json('cart.shipping_fee');

        $this->fakeSumUp(createCheckout: ['id' => 'chk_cmp', 'status' => 'PENDING']);
        $this->withHeader('X-Cart-Session', 'compare')->postJson('/api/checkout', [
            'guest_email' => 'cliente@exemple.fr',
            'guest_first_name' => 'Alice',
            'guest_last_name' => 'Martin',
            'cgv_accepted' => true,
            'shipping_phone' => '0612345678',
            'shipping_address_line1' => '1 rue des Lilas',
            'shipping_postal_code' => '31000',
            'shipping_city' => 'Toulouse',
            'shipping_country' => 'FR',
        ])->assertOk();

        $debite = (float) Order::sole()->shipping_fee;

        $this->assertSame(9.0, (float) $affiche, 'Le maximum des deux galeries doit être affiché.');
        $this->assertSame((float) $affiche, $debite, 'Le port débité doit égaler le port affiché.');
    }
}
