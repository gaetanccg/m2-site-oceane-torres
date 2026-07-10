<?php

namespace Tests\Feature\Payment;

use App\Models\Cart;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * POST /checkout — création d'une commande depuis le panier (OrderController@createFromCart).
 */
class CheckoutTest extends TestCase
{
    use CreatesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
        Mail::fake();
    }

    private function checkout(string $sessionId, array $body = []): TestResponse
    {
        return $this->withHeader('X-Cart-Session', $sessionId)->postJson('/api/checkout', array_merge([
            'guest_email' => 'client@example.com',
            'guest_first_name' => 'Jean',
            'guest_last_name' => 'Dupont',
            'cgv_accepted' => true,
        ], $body));
    }

    public function test_empty_cart_returns_400(): void
    {
        // Panier existant mais vide.
        Cart::factory()->create(['session_id' => 'empty-session']);

        $response = $this->checkout('empty-session');

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Le panier est vide.']);
    }

    public function test_paid_checkout_creates_pending_order_and_initiates_sumup_payment(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_new_123', 'status' => 'PENDING']);
        $this->makeGuestCartWithDigitalItems(count: 2, sessionId: 'paid-session');

        $response = $this->checkout('paid-session');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.checkout_id', 'chk_new_123')
            ->assertJsonPath('order.total', 26);

        $order = Order::first();
        $this->assertSame('pending', $order->status);
        $this->assertSame('chk_new_123', $order->sumup_checkout_id);

        // Une ligne de paiement SumUp pending a été créée.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'sumup',
            'status' => 'pending',
        ]);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_free_order_when_gift_code_covers_full_cart_skips_sumup(): void
    {
        Http::fake(); // Toute requête SumUp ferait échouer l'assertNothingSent.

        $cart = $this->makeGuestCartWithDigitalItems(count: 1, sessionId: 'free-session');
        $this->attachGiftCode($cart, GiftCode::factory()->fixed(100)->create());

        $response = $this->checkout('free-session');

        $response->assertOk()
            ->assertJsonPath('payment.free', true)
            ->assertJsonPath('order.total', 0);

        // Aucun checkout SumUp n'a été créé, la commande reste pending.
        Http::assertNothingSent();
        $this->assertSame('pending', Order::first()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_print_item_without_shipping_address_is_rejected(): void
    {
        $this->makeGuestCartWithPrintItem('print-session');

        $response = $this->checkout('print-session'); // pas d'adresse fournie

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address_line1', 'shipping_postal_code', 'shipping_city']);
    }

    public function test_cgv_must_be_accepted(): void
    {
        $this->makeGuestCartWithDigitalItems(sessionId: 'cgv-session');

        $response = $this->checkout('cgv-session', ['cgv_accepted' => false]);

        $response->assertStatus(422)->assertJsonValidationErrors(['cgv_accepted']);
    }

    public function test_guest_email_is_required_for_guest_checkout(): void
    {
        $this->makeGuestCartWithDigitalItems(sessionId: 'noemail-session');

        $response = $this->checkout('noemail-session', ['guest_email' => '']);

        $response->assertStatus(422)->assertJsonValidationErrors(['guest_email']);
    }

    public function test_creating_new_checkout_expires_previous_pending_orders(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_second', 'status' => 'PENDING']);
        $cart = $this->makeGuestCartWithDigitalItems(sessionId: 'reuse-session');

        // Une première commande pending rattachée au même panier/session.
        $firstOrder = Order::factory()->pending()->create(['cart_id' => $cart->id]);

        $this->checkout('reuse-session')->assertOk();

        $this->assertSame('expired', $firstOrder->fresh()->status);
    }
}
