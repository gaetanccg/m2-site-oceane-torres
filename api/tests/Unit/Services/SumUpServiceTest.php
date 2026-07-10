<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Services\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SumUpService : seul point où passent les appels HTTP vers SumUp.
 * Tous les tests stubbent le client HTTP via Http::fake().
 */
class SumUpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_constructor_throws_when_api_key_missing(): void
    {
        config(['sumup.api_key' => null]);

        $this->expectException(\RuntimeException::class);
        new SumUpService;
    }

    public function test_constructor_throws_when_merchant_code_missing(): void
    {
        config(['sumup.merchant_code' => null]);

        $this->expectException(\RuntimeException::class);
        new SumUpService;
    }

    public function test_create_checkout_persists_checkout_id_on_success(): void
    {
        Http::fake([
            'api.sumup.com/v0.1/checkouts' => Http::response(['id' => 'chk_abc', 'status' => 'PENDING']),
        ]);
        $order = Order::factory()->pending()->create();

        $data = (new SumUpService)->createCheckout($order);

        $this->assertSame('chk_abc', $data['id']);
        $this->assertSame('chk_abc', $order->fresh()->sumup_checkout_id);

        // Le montant envoyé correspond au total de la commande.
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sumup.com/v0.1/checkouts'
            && (float) $request['amount'] === (float) $order->total);
    }

    public function test_create_checkout_throws_and_leaves_order_untouched_on_failure(): void
    {
        Http::fake(['api.sumup.com/v0.1/checkouts' => Http::response(['error' => 'bad'], 400)]);
        $order = Order::factory()->pending()->create();

        $this->expectException(\Exception::class);

        try {
            (new SumUpService)->createCheckout($order);
        } finally {
            $this->assertNull($order->fresh()->sumup_checkout_id);
        }
    }

    public function test_deactivate_checkout_returns_true_on_success(): void
    {
        Http::fake(['api.sumup.com/v0.1/checkouts/*' => Http::response([], 204)]);

        $this->assertTrue((new SumUpService)->deactivateCheckout('chk_x'));
    }

    public function test_deactivate_checkout_returns_false_without_throwing_on_error(): void
    {
        Http::fake(['api.sumup.com/v0.1/checkouts/*' => Http::response([], 500)]);

        $this->assertFalse((new SumUpService)->deactivateCheckout('chk_x'));
    }
}
