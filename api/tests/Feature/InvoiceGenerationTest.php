<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(array $overrides = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => 'paid',
            'subtotal' => 30.00,
            'total' => 30.00,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'paid_at' => now(),
        ], $overrides));

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 3,
            'price' => 10.00,
            'photo_title' => 'portrait-01',
        ]);

        return $order->fresh(['items']);
    }

    public function test_it_produces_a_pdf_on_storage(): void
    {
        Storage::fake('minio');

        $invoice = app(InvoiceService::class)->generateForOrder($this->paidOrder());

        $this->assertNotNull($invoice->file_path);
        Storage::disk('minio')->assertExists($invoice->file_path);
        $this->assertGreaterThan(1000, strlen(Storage::disk('minio')->get($invoice->file_path)));
    }

    public function test_it_is_idempotent(): void
    {
        Storage::fake('minio');
        $order = $this->paidOrder();
        $service = app(InvoiceService::class);

        $first = $service->generateForOrder($order);
        $second = $service->generateForOrder($order->fresh(['items']));

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->invoice_number, $second->invoice_number);
    }

    public function test_it_renders_with_a_promo_code_applied(): void
    {
        Storage::fake('minio');

        $order = $this->paidOrder([
            'discount_amount' => 5.00,
            'total' => 25.00,
            'gift_code' => 'PROMO5',
        ]);

        $invoice = app(InvoiceService::class)->generateForOrder($order);

        $this->assertNotNull($invoice->file_path);
        Storage::disk('minio')->assertExists($invoice->file_path);
    }

    public function test_invoice_numbers_are_sequential(): void
    {
        Storage::fake('minio');
        $service = app(InvoiceService::class);

        $first = $service->generateForOrder($this->paidOrder());
        $second = $service->generateForOrder($this->paidOrder());

        $seq = fn (string $n) => (int) substr($n, strrpos($n, '-') + 1);

        $this->assertSame($seq($first->invoice_number) + 1, $seq($second->invoice_number));
    }
}
