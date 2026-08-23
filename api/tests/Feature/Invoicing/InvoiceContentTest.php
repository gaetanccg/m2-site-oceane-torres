<?php

namespace Tests\Feature\Invoicing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
    }

    private function render(Order $order): string
    {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'photo_id' => Photo::factory()->create()->id,
            'price' => 13,
            'quantity' => 2,
        ]);

        return view('pdf.invoice', [
            'order' => $order->fresh('items'),
            'invoice' => (object) ['invoice_number' => 'FA-2026-00001', 'generated_at' => now()],
        ])->render();
    }

    /** « TTC » n'a pas de sens en franchise de TVA, et figurait juste au-dessus de la mention art. 293 B. */
    public function test_les_libelles_ne_mentionnent_plus_la_tva_incluse(): void
    {
        $html = $this->render(Order::factory()->paid()->create());

        $this->assertStringNotContainsString('Prix TTC', $html);
        $this->assertStringNotContainsString('Total TTC', $html);
        $this->assertStringContainsString('Montant', $html);
        $this->assertStringContainsString('TVA non applicable, art. 293 B du CGI', $html);
    }

    public function test_l_adresse_de_livraison_figure_sur_la_facture(): void
    {
        $order = Order::factory()->paid()->create([
            'shipping_address_line1' => '12 rue des Lilas',
            'shipping_postal_code' => '31000',
            'shipping_city' => 'Toulouse',
            'shipping_country' => 'FR',
        ]);

        $html = $this->render($order);

        $this->assertStringContainsString('12 rue des Lilas', $html);
        $this->assertStringContainsString('31000 Toulouse', $html);
        $this->assertStringContainsString('FR', $html);
    }

    /** Une commande numérique n'a pas d'adresse de livraison : on retombe sur le compte. */
    public function test_l_adresse_du_compte_sert_de_repli(): void
    {
        $user = User::factory()->create([
            'address_line1' => '5 impasse du Port',
            'postal_code' => '44000',
            'city' => 'Nantes',
        ]);
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
            'shipping_address_line1' => null,
        ]);

        $html = $this->render($order);

        $this->assertStringContainsString('5 impasse du Port', $html);
        $this->assertStringContainsString('44000 Nantes', $html);
    }

    public function test_sans_aucune_adresse_la_facture_reste_valide(): void
    {
        $order = Order::factory()->paid()->create([
            'user_id' => null,
            'shipping_address_line1' => null,
        ]);

        $html = $this->render($order);

        $this->assertStringContainsString('FA-2026-00001', $html);
        $this->assertNull($order->invoiceAddress());
    }

    public function test_la_quantite_et_le_total_de_ligne_sont_corrects(): void
    {
        $html = $this->render(Order::factory()->paid()->create());

        $this->assertStringContainsString('2 × 13,00 €', $html);
        $this->assertStringContainsString('26,00 €', $html);
    }
}
