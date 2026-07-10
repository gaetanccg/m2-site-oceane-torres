<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Services\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * POST /payments/sumup/cancel-checkout — abandon volontaire du paiement.
 */
class CancelCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // deactivateCheckout fait un DELETE HTTP.
    }

    public function test_cancels_a_pending_order_with_checkout(): void
    {
        $order = Order::factory()->pending()->withCheckout('chk_cancel')->create();

        $response = $this->postJson('/api/payments/sumup/cancel-checkout', ['order_id' => $order->id]);

        $response->assertOk()->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('expired', $order->status);
        $this->assertNull($order->sumup_checkout_id);
    }

    public function test_non_pending_order_returns_400(): void
    {
        $order = Order::factory()->paid()->create();

        $response = $this->postJson('/api/payments/sumup/cancel-checkout', ['order_id' => $order->id]);

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_returns_409_when_order_paid_between_find_and_update(): void
    {
        // On simule la race : le service deactivateCheckout marque la commande payée
        // (comme le ferait un webhook arrivé pendant le round-trip réseau).
        $order = Order::factory()->pending()->withCheckout('chk_race')->create();

        $mock = Mockery::mock(SumUpService::class);
        $mock->shouldReceive('deactivateCheckout')->once()->andReturnUsing(function () use ($order) {
            $order->markAsPaid('txn_race');

            return true;
        });
        $this->app->instance(SumUpService::class, $mock);

        $response = $this->postJson('/api/payments/sumup/cancel-checkout', ['order_id' => $order->id]);

        $response->assertStatus(409)
            ->assertJsonPath('already_paid', true)
            ->assertJsonPath('order_id', $order->id);
    }
}
