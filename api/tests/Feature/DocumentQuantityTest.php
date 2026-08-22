<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Mail\PrintOrderNotificationMail;
use App\Mail\SchoolOrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class DocumentQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithQuantity(int $quantity, float $price, string $productType = 'digital'): Order
    {
        $order = Order::factory()->create([
            'status' => 'paid',
            'subtotal' => $price * $quantity,
            'total' => $price * $quantity,
            'shipping_fee' => 0,
            'discount_amount' => 0,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_type' => $productType,
            'quantity' => $quantity,
            'price' => $price,
            'photo_title' => 'portrait-01',
        ]);

        return $order->fresh(['items']);
    }

    public function test_invoice_shows_unit_price_and_line_total(): void
    {
        $order = $this->orderWithQuantity(3, 10.00);

        $html = View::make('pdf.invoice', [
            'order' => $order,
            'invoice' => (object) ['invoice_number' => 'F-2026-00001', 'generated_at' => now()],
        ])->render();

        $this->assertStringContainsString('3 × 10,00 €', $html);
        $this->assertStringContainsString('30,00 €', $html);
    }

    public function test_invoice_lines_add_up_to_the_subtotal(): void
    {
        $order = $this->orderWithQuantity(4, 6.00);

        $html = View::make('pdf.invoice', [
            'order' => $order,
            'invoice' => (object) ['invoice_number' => 'F-2026-00002', 'generated_at' => now()],
        ])->render();

        $this->assertStringContainsString('24,00 €', $html);
        $this->assertSame(24.00, (float) $order->subtotal);
    }

    public function test_invoice_omits_multiplier_for_single_unit(): void
    {
        $order = $this->orderWithQuantity(1, 13.00);

        $html = View::make('pdf.invoice', [
            'order' => $order,
            'invoice' => (object) ['invoice_number' => 'F-2026-00003', 'generated_at' => now()],
        ])->render();

        $this->assertStringNotContainsString('1 × 13,00 €', $html);
        $this->assertStringContainsString('13,00 €', $html);
    }

    public function test_order_confirmation_email_shows_quantity(): void
    {
        $order = $this->orderWithQuantity(3, 10.00);

        $html = (new OrderConfirmationMail($order, 'https://exemple.test/telechargement'))->render();

        $this->assertStringContainsString('3 × 10,00 €', $html);
        $this->assertStringContainsString('30,00 €', $html);
    }

    public function test_school_order_confirmation_email_shows_quantity(): void
    {
        $order = $this->orderWithQuantity(5, 6.00, 'print_scolaire');

        $html = (new SchoolOrderConfirmationMail($order))->render();

        $this->assertStringContainsString('5 × 6,00 €', $html);
        $this->assertStringContainsString('30,00 €', $html);
    }

    public function test_print_notification_states_the_number_of_copies(): void
    {
        $order = $this->orderWithQuantity(5, 10.00, 'print_10x15');

        $html = (new PrintOrderNotificationMail($order))->render();

        $this->assertStringContainsString('5 exemplaires', $html);
        $this->assertStringContainsString('50,00 €', $html);
    }

    public function test_print_notification_uses_singular_for_one_copy(): void
    {
        $order = $this->orderWithQuantity(1, 10.00, 'print_10x15');

        $html = (new PrintOrderNotificationMail($order))->render();

        $this->assertStringContainsString('1 exemplaire', $html);
        $this->assertStringNotContainsString('1 exemplaires', $html);
    }
}
