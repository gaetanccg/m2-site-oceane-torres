<?php

namespace Tests\Feature\Invoicing;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * invoices.order_id etait en CASCADE : supprimer une commande detruisait sa facture, que
 * le Code de commerce impose de conserver dix ans. Et destroy() n'avait aucune garde.
 */
class OrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function paidOrderWithInvoice(): Order
    {
        $order = Order::factory()->paid()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'photo_id' => Photo::factory()->create()->id,
        ]);
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00001',
            'generated_at' => now(),
        ]);

        return $order;
    }

    public function test_une_commande_payee_ne_peut_pas_etre_supprimee(): void
    {
        $order = $this->paidOrderWithInvoice();

        $this->deleteJson("/api/admin/orders/{$order->id}")->assertStatus(409);

        $this->assertNotNull(Order::find($order->id));
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
    }

    public function test_une_commande_non_payee_mais_facturee_ne_peut_pas_l_etre_non_plus(): void
    {
        $order = Order::factory()->pending()->create();
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'FA-2026-00002',
            'generated_at' => now(),
        ]);

        $this->deleteJson("/api/admin/orders/{$order->id}")->assertStatus(409);
    }

    public function test_une_commande_expiree_sans_facture_reste_supprimable(): void
    {
        $order = Order::factory()->create(['status' => 'expired']);

        $this->deleteJson("/api/admin/orders/{$order->id}")->assertOk();

        $this->assertNull(Order::find($order->id));
    }

    /** SoftDeletes : la suppression cache la commande sans perdre la trace. */
    public function test_une_suppression_est_reversible(): void
    {
        $order = Order::factory()->create(['status' => 'expired']);

        $this->deleteJson("/api/admin/orders/{$order->id}")->assertOk();

        $this->assertNull(Order::find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id)->deleted_at);
    }

    /** Defense en profondeur : la base refuse, meme si le code se trompe. */
    public function test_la_base_refuse_de_detruire_une_commande_facturee(): void
    {
        $order = $this->paidOrderWithInvoice();

        $this->expectException(QueryException::class);

        $order->forceDelete();
    }

    public function test_les_lignes_survivent_a_une_suppression_douce(): void
    {
        $order = Order::factory()->create(['status' => 'expired']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'photo_id' => Photo::factory()->create()->id,
        ]);

        $this->deleteJson("/api/admin/orders/{$order->id}")->assertOk();

        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());
    }
}
