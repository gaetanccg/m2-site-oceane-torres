<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une commande ne porte qu'UNE facture. `generateForOrder` verifiait l'existence hors
 * transaction, et trois appelants peuvent se declencher ensemble : webhook SumUp, polling du
 * front, action admin. Le verrou consultatif protegeait le NUMERO, pas l'existence.
 */
class InvoiceUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_base_refuse_une_seconde_facture_pour_la_meme_commande(): void
    {
        $order = Order::factory()->create();
        $premiere = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00001',
            'generated_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Invoice::create([
            'order_id' => $premiere->order_id,
            'invoice_number' => 'FA-2026-00002',
            'generated_at' => now(),
        ]);
    }

    public function test_generer_deux_fois_rend_la_meme_facture(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);
        $service = app(InvoiceService::class);

        $a = $service->generateForOrder($order);
        $b = $service->generateForOrder($order->fresh());

        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->invoice_number, $b->invoice_number);
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    /**
     * Le cas de course : une facture apparait entre la verification et l'insertion. Le perdant
     * doit recuperer celle du gagnant, pas remonter l'erreur SQL a l'utilisateur.
     */
    public function test_le_perdant_de_la_course_recupere_la_facture_du_gagnant(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);

        $concurrente = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-09999',
            'generated_at' => now(),
        ]);

        // On force le service a croire la commande sans facture : l'instance chargee ici n'a
        // pas vu l'insertion concurrente, exactement comme le webhook et le polling.
        $order->unsetRelation('invoice');

        $recue = app(InvoiceService::class)->generateForOrder($order);

        $this->assertSame($concurrente->id, $recue->id);
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_une_facture_sans_pdf_conserve_son_numero(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);
        $facture = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00042',
            'generated_at' => now(),
            'file_path' => null,
        ]);

        $regeneree = app(InvoiceService::class)->generateForOrder($order);

        $this->assertSame('FA-2026-00042', $regeneree->invoice_number);
        $this->assertSame($facture->id, $regeneree->id);
    }
}
