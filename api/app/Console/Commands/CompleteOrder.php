<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteOrder extends Command
{
    protected $signature = 'order:complete {order_number} {transaction_id}';

    protected $description = 'Manually complete a failed/pending order (mark as paid, generate invoice, send emails)';

    public function handle(OrderService $orderService): int
    {
        $order = Order::where('order_number', $this->argument('order_number'))->first();

        if (! $order) {
            $this->error('Order not found.');

            return self::FAILURE;
        }

        if ($order->isPaid()) {
            $this->info("Order {$order->order_number} is already paid.");

            return self::SUCCESS;
        }

        $this->info("Order: {$order->order_number}");
        $this->info("Status: {$order->status}");
        $this->info("Total: {$order->total}€");

        if (! $this->confirm('Complete this order?')) {
            return self::SUCCESS;
        }

        // Reset to pending so completeOrder accepts it
        if ($order->status === 'failed') {
            DB::table('orders')->where('id', $order->id)->update(['status' => 'pending']);
            DB::table('payments')->where('order_id', $order->id)->update(['status' => 'pending']);
            $order->refresh();
        }

        $orderService->completeOrder($order, $this->argument('transaction_id'));

        $this->info("Order {$order->order_number} completed successfully.");
        $this->info('Invoice generated, confirmation email sent.');

        return self::SUCCESS;
    }
}
