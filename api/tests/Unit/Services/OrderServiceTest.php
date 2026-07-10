<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Mail\OrderConfirmationMail;
use App\Mail\PrintOrderNotificationMail;
use App\Mail\SchoolOrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Cœur logique du paiement : initiatePayment / completeOrder / completeFreeOrder.
 * SumUpService (seul appel réseau) est mocké ; les effets de bord (facture, mails)
 * sont neutralisés via Storage::fake + Mail::fake.
 */
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
        Mail::fake();
    }

    /** Injecte un mock de SumUpService et retourne l'OrderService résolu du container. */
    private function serviceWithSumUp(\Closure $expectations): OrderService
    {
        $mock = Mockery::mock(SumUpService::class);
        $expectations($mock);
        $this->app->instance(SumUpService::class, $mock);

        return $this->app->make(OrderService::class);
    }

    public function test_initiate_payment_rejects_non_pending_order(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m->shouldReceive('createCheckout')->never());
        $order = Order::factory()->paid()->create();

        $this->expectException(BusinessException::class);
        $service->initiatePayment($order);
    }

    public function test_initiate_payment_reuses_existing_pending_checkout(): void
    {
        $service = $this->serviceWithSumUp(function ($m) {
            $m->shouldReceive('getCheckout')->once()->andReturn(['status' => 'PENDING']);
            $m->shouldReceive('createCheckout')->never();
        });
        $order = Order::factory()->pending()->withCheckout('chk_reuse')->create();

        $result = $service->initiatePayment($order);

        $this->assertSame('chk_reuse', $result['checkout_id']);
    }

    public function test_initiate_payment_completes_and_throws_409_when_checkout_already_paid(): void
    {
        $service = $this->serviceWithSumUp(function ($m) {
            $m->shouldReceive('getCheckout')->once()
                ->andReturn(['status' => 'PAID', 'transaction_id' => 'txn_done']);
        });
        $order = Order::factory()->pending()->withCheckout('chk_paid')->create();

        try {
            $service->initiatePayment($order);
            $this->fail('Expected BusinessException 409');
        } catch (BusinessException $e) {
            $this->assertSame(409, $e->getHttpStatus());
        }

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_initiate_payment_creates_a_single_payment_row(): void
    {
        $service = $this->serviceWithSumUp(function ($m) {
            $m->shouldReceive('createCheckout')->andReturn(['id' => 'chk_pay', 'status' => 'PENDING']);
        });
        $order = Order::factory()->pending()->create();

        $service->initiatePayment($order);
        $service->initiatePayment($order); // 2e tentative → update, pas de doublon

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'sumup',
            'provider_payment_id' => 'chk_pay',
            'status' => 'pending',
        ]);
    }

    public function test_complete_order_is_idempotent(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m);
        $order = Order::factory()->paid()->create(['sumup_transaction_id' => 'txn_first']);

        $service->completeOrder($order, 'txn_second');

        // Déjà payée → le transaction id d'origine n'est pas écrasé.
        $this->assertSame('txn_first', $order->fresh()->sumup_transaction_id);
        Mail::assertNothingQueued();
    }

    public function test_complete_order_rejects_unexpected_state(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m);
        $order = Order::factory()->expired()->create();

        $this->expectException(BusinessException::class);
        $service->completeOrder($order, 'txn_x');
    }

    public function test_complete_free_order_rejects_positive_total(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m);
        $order = Order::factory()->pending()->create(['subtotal' => 13, 'total' => 13]);

        $this->expectException(BusinessException::class);
        $service->completeFreeOrder($order);
    }

    public function test_complete_free_order_marks_paid_for_zero_total(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m);
        $order = Order::factory()->free()->pending()->create();

        $service->completeFreeOrder($order);

        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_complete_order_queues_confirmation_and_print_notification(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m);
        $order = Order::factory()->pending()->create();
        OrderItem::factory()->print('print_10x15')->create(['order_id' => $order->id]);

        $service->completeOrder($order, 'txn_print');

        Mail::assertQueued(OrderConfirmationMail::class);
        Mail::assertQueued(PrintOrderNotificationMail::class);
    }

    public function test_complete_order_for_school_uses_school_mail_and_skips_print_notif(): void
    {
        $service = $this->serviceWithSumUp(fn ($m) => $m);
        $order = Order::factory()->pending()->create();
        // Commande 100 % scolaire → mail dédié, pas de notif print générique.
        OrderItem::factory()->print('print_scolaire')->create(['order_id' => $order->id]);

        $service->completeOrder($order, 'txn_school');

        Mail::assertQueued(SchoolOrderConfirmationMail::class);
        Mail::assertNotQueued(PrintOrderNotificationMail::class);
    }

    public function test_verify_and_update_order_completes_on_paid_in_production(): void
    {
        // Hors sandbox : le raccourci d'auto-complétion ne s'applique pas, getCheckout est appelé.
        config(['sumup.environment' => 'production']);
        $service = $this->serviceWithSumUp(function ($m) {
            $m->shouldReceive('getCheckout')->once()->andReturn(['status' => 'PAID', 'transaction_id' => 'txn_prod']);
        });
        $order = Order::factory()->pending()->withCheckout('chk_prod')->create();

        $result = $service->verifyAndUpdateOrder('chk_prod');

        $this->assertTrue($result->isPaid());
    }

    public function test_verify_and_update_order_leaves_order_unchanged_when_not_paid(): void
    {
        config(['sumup.environment' => 'production']);
        $service = $this->serviceWithSumUp(function ($m) {
            $m->shouldReceive('getCheckout')->once()->andReturn(['status' => 'PENDING']);
        });
        $order = Order::factory()->pending()->withCheckout('chk_wait')->create();

        $result = $service->verifyAndUpdateOrder('chk_wait');

        // Ni payée ni échouée : seuls le webhook / la réconciliation marquent failed.
        $this->assertSame('pending', $result->status);
    }
}
