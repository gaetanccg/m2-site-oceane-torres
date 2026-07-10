<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POST /payments/sumup/return — webhook CHECKOUT_STATUS_CHANGED.
 *
 * Sécurité : le webhook ne fait JAMAIS confiance au payload ; il ré-interroge
 * l'API SumUp (getCheckout) pour connaître le vrai statut. Ces appels sont donc
 * toujours mockés via Http::fake().
 */
class SumUpWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
        Mail::fake();
    }

    private function fakeCheckoutStatus(string $status, ?string $transactionId = null): void
    {
        Http::fake([
            'api.sumup.com/v0.1/checkouts/*' => Http::response(array_filter([
                'status' => $status,
                'transaction_id' => $transactionId,
            ])),
        ]);
    }

    public function test_missing_id_is_acknowledged_without_retry(): void
    {
        $response = $this->postJson('/api/payments/sumup/return', ['event_type' => 'UNKNOWN']);

        $response->assertOk()->assertJson(['received' => true]);
    }

    public function test_unknown_order_is_acknowledged(): void
    {
        $this->fakeCheckoutStatus('PAID');

        $response = $this->postJson('/api/payments/sumup/return', ['id' => 'chk_unknown']);

        $response->assertOk()->assertJson(['received' => true]);
        // Aucun appel à SumUp : on s'arrête avant (order introuvable).
        Http::assertNothingSent();
    }

    public function test_already_paid_order_is_acknowledged_without_calling_sumup(): void
    {
        Http::fake();
        $order = Order::factory()->paid()->withCheckout('chk_paid')->create();

        $response = $this->postJson('/api/payments/sumup/return', ['id' => 'chk_paid']);

        $response->assertOk()->assertJson(['received' => true]);
        Http::assertNothingSent();
    }

    public function test_verified_paid_completes_the_order(): void
    {
        $this->fakeCheckoutStatus('PAID', 'txn_ok');
        $order = Order::factory()->pending()->withCheckout('chk_topay')->create();

        $response = $this->postJson('/api/payments/sumup/return', ['id' => 'chk_topay']);

        $response->assertOk()->assertJson(['received' => true]);
        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('txn_ok', $order->sumup_transaction_id);
    }

    public function test_verified_failed_marks_the_order_failed(): void
    {
        $this->fakeCheckoutStatus('FAILED');
        $order = Order::factory()->pending()->withCheckout('chk_ko')->create();

        $response = $this->postJson('/api/payments/sumup/return', ['id' => 'chk_ko']);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertTrue($order->fresh()->isFailed());
    }

    public function test_pending_status_leaves_order_untouched(): void
    {
        $this->fakeCheckoutStatus('PENDING');
        $order = Order::factory()->pending()->withCheckout('chk_wait')->create();

        $response = $this->postJson('/api/payments/sumup/return', ['id' => 'chk_wait']);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_transient_error_returns_503_for_retry(): void
    {
        // getCheckout renvoie 500 → SumUpService lève une exception → webhook 503.
        Http::fake(['api.sumup.com/v0.1/checkouts/*' => Http::response([], 500)]);
        Order::factory()->pending()->withCheckout('chk_boom')->create();

        $response = $this->postJson('/api/payments/sumup/return', ['id' => 'chk_boom']);

        $response->assertStatus(503)
            ->assertJson(['received' => false, 'error' => 'transient']);
    }
}
