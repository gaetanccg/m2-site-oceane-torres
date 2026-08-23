<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * generateForOrder retournait la facture existante SANS regenerer son PDF. Le bouton
 * « telecharger la facture » de l'admin l'appelle justement pour reparer : il ne
 * reparait donc jamais rien, et une facture sans fichier l'etait definitivement.
 */
class InvoicePdfRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
    }

    private function paidOrder(): Order
    {
        $order = Order::factory()->paid()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'photo_id' => Photo::factory()->create()->id,
        ]);

        return $order;
    }

    public function test_une_facture_sans_pdf_est_reparee_en_gardant_son_numero(): void
    {
        $order = $this->paidOrder();
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00042',
            'generated_at' => now(),
        ]);

        app(InvoiceService::class)->generateForOrder($order);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->file_path, 'Le PDF doit avoir été régénéré.');
        $this->assertSame('FA-2026-00042', $invoice->invoice_number, 'Le numéro ne doit pas changer.');
        Storage::disk('minio')->assertExists($invoice->file_path);
    }

    public function test_la_reparation_ne_cree_pas_de_seconde_facture(): void
    {
        $order = $this->paidOrder();
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00043',
            'generated_at' => now(),
        ]);

        app(InvoiceService::class)->generateForOrder($order);

        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_une_facture_complete_n_est_pas_regeneree(): void
    {
        $order = $this->paidOrder();
        Storage::disk('minio')->put('invoices/existant.pdf', 'pdf-original');
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00044',
            'generated_at' => now(),
            'file_path' => 'invoices/existant.pdf',
        ]);

        app(InvoiceService::class)->generateForOrder($order);

        $this->assertSame('invoices/existant.pdf', $invoice->fresh()->file_path);
        $this->assertSame('pdf-original', Storage::disk('minio')->get('invoices/existant.pdf'));
    }

    public function test_la_commande_repare_les_factures_orphelines(): void
    {
        $order = $this->paidOrder();
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00045',
            'generated_at' => now(),
        ]);

        $this->artisan('invoices:repair-pdf')->assertSuccessful();

        $this->assertNotNull(Invoice::where('invoice_number', 'FA-2026-00045')->first()->file_path);
    }

    public function test_a_blanc_la_commande_ne_regenere_rien(): void
    {
        $order = $this->paidOrder();
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00046',
            'generated_at' => now(),
        ]);

        $this->artisan('invoices:repair-pdf', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull(Invoice::where('invoice_number', 'FA-2026-00046')->first()->file_path);
    }

    public function test_le_bouton_admin_repare_et_renvoie_un_lien(): void
    {
        $this->actingAsAdmin();
        $order = $this->paidOrder();
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00047',
            'generated_at' => now(),
        ]);

        $this->getJson("/api/admin/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
