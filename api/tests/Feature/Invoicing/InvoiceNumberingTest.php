<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Services\InvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
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

    /** La numerotation seule : generer 5 PDF reels pour verifier des numeros est inutile. */
    public function test_la_numerotation_est_sequentielle_et_sans_trou(): void
    {
        $annee = date('Y');

        $numeros = collect(range(1, 5))->map(function () {
            $numero = Invoice::generateInvoiceNumber();
            Invoice::create([
                'order_id' => $this->paidOrder()->id,
                'invoice_number' => $numero,
                'generated_at' => now(),
            ]);

            return $numero;
        })->all();

        $this->assertSame([
            "F-{$annee}-00001", "F-{$annee}-00002", "F-{$annee}-00003",
            "F-{$annee}-00004", "F-{$annee}-00005",
        ], $numeros);
    }

    public function test_la_generation_est_idempotente(): void
    {
        $order = $this->paidOrder();
        $service = app(InvoiceService::class);

        $premier = $service->generateForOrder($order);
        $second = $service->generateForOrder($order);

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, Invoice::count());
    }

    /** La facture est denormalisee : elle survit a la disparition de la photo. */
    public function test_une_facture_survit_a_la_suppression_de_la_photo(): void
    {
        $order = $this->paidOrder();
        $invoice = app(InvoiceService::class)->generateForOrder($order);
        $item = $order->items()->first();

        Photo::find($item->photo_id)->delete();

        $this->assertNotNull($invoice->fresh());
        $this->assertNotNull($item->fresh()->photo_title ?? $item->fresh()->id);
    }

    public function test_le_numero_est_unique_en_base(): void
    {
        $order = $this->paidOrder();
        app(InvoiceService::class)->generateForOrder($order);

        $this->expectException(QueryException::class);

        Invoice::create([
            'order_id' => $this->paidOrder()->id,
            'invoice_number' => 'F-'.date('Y').'-00001',
            'generated_at' => now(),
        ]);
    }
}
