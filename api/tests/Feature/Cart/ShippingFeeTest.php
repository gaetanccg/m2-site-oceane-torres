<?php

namespace Tests\Feature\Cart;

use App\Models\CartItem;
use App\Models\Gallery;
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
}
