<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POST /checkout/confirm-free — finalisation d'une commande gratuite (total 0 €).
 */
class ConfirmFreeOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
        Mail::fake();
    }

    public function test_confirms_a_free_pending_order(): void
    {
        $order = Order::factory()->free()->pending()->create();

        $response = $this->postJson('/api/checkout/confirm-free', ['order_id' => $order->id]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_is_idempotent_when_already_paid(): void
    {
        $order = Order::factory()->free()->paid()->create();

        $response = $this->postJson('/api/checkout/confirm-free', ['order_id' => $order->id]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_rejects_order_with_positive_total(): void
    {
        // total > 0 : completeFreeOrder lève BusinessException(400).
        $order = Order::factory()->pending()->create(['subtotal' => 13, 'total' => 13]);

        $response = $this->postJson('/api/checkout/confirm-free', ['order_id' => $order->id]);

        $response->assertStatus(400)->assertJsonPath('success', false);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_rejects_non_pending_order(): void
    {
        $order = Order::factory()->free()->failed()->create();

        $response = $this->postJson('/api/checkout/confirm-free', ['order_id' => $order->id]);

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_unknown_order_fails_validation(): void
    {
        $response = $this->postJson('/api/checkout/confirm-free', [
            'order_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['order_id']);
    }
}
