<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 *
 * Par défaut : paiement SumUp d'achat photo, en attente.
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'sumup',
            'provider_payment_id' => null,
            'amount' => fake()->randomFloat(2, 10, 200),
            'currency' => 'EUR',
            'status' => 'pending',
            'type' => 'photo_purchase',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'provider_payment_id' => 'txn_'.fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
            'amount' => $order->total,
        ]);
    }
}
