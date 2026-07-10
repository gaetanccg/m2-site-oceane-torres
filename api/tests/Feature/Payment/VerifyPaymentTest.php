<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POST /payments/sumup/verify — vérification du statut de paiement (polling frontend).
 *
 * ⚠️ Environnement de test = sandbox : OrderService::verifyAndUpdateOrder()
 * auto-complète la commande dès qu'un checkout_id est présent, sans appeler
 * l'API SumUp. Ces tests valident donc ce comportement sandbox.
 */
class VerifyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
        Mail::fake();
    }

    public function test_returns_paid_immediately_when_order_already_paid(): void
    {
        $order = Order::factory()->paid()->create();

        $response = $this->postJson('/api/payments/sumup/verify', ['order_id' => $order->id]);

        $response->assertOk()->assertJsonPath('status', 'paid');
    }

    public function test_returns_pending_when_no_checkout_initiated(): void
    {
        $order = Order::factory()->pending()->create(['sumup_checkout_id' => null]);

        $response = $this->postJson('/api/payments/sumup/verify', ['order_id' => $order->id]);

        $response->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('message', 'Paiement non initie.');
    }

    public function test_sandbox_auto_completes_order_with_checkout_id(): void
    {
        $order = Order::factory()->pending()->withCheckout('chk_sandbox')->create();

        $response = $this->postJson('/api/payments/sumup/verify', ['order_id' => $order->id]);

        $response->assertOk()->assertJsonPath('status', 'paid');
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_unknown_order_fails_validation(): void
    {
        $response = $this->postJson('/api/payments/sumup/verify', [
            'order_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['order_id']);
    }
}
