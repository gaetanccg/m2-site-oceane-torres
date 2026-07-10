<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 *
 * Par défaut : commande invité, en attente (pending), sans checkout SumUp initié.
 * `order_number` est généré par le boot du modèle (verrou consultatif Postgres).
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 200);

        return [
            'guest_email' => fake()->safeEmail(),
            'guest_first_name' => fake()->firstName(),
            'guest_last_name' => fake()->lastName(),
            'subtotal' => $subtotal,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'total' => $subtotal,
            'currency' => 'EUR',
            'status' => 'pending',
            'shipping_country' => 'FR',
            'cgv_accepted' => true,
            'cgv_accepted_at' => now(),
            'cgv_version' => '1.0',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    /**
     * Commande payée : statut paid, paid_at renseigné et token de téléchargement
     * (7 jours) posé dans metadata, comme le fait completeOrder().
     */
    public function paid(?string $downloadToken = null): static
    {
        return $this->state(function (array $attributes) use ($downloadToken) {
            $state = [
                'status' => 'paid',
                'paid_at' => now(),
                'sumup_transaction_id' => 'txn_'.fake()->uuid(),
            ];

            if ($downloadToken !== null) {
                $state['metadata'] = array_merge($attributes['metadata'] ?? [], [
                    'download_token' => $downloadToken,
                    'download_token_expires_at' => now()->addDays(7)->toIso8601String(),
                ]);
            }

            return $state;
        });
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => 'expired']);
    }

    /**
     * Checkout SumUp déjà initié (paiement démarré mais pas confirmé).
     */
    public function withCheckout(?string $checkoutId = null): static
    {
        return $this->state(fn () => [
            'sumup_checkout_id' => $checkoutId ?? 'chk_'.fake()->uuid(),
        ]);
    }

    /**
     * Commande gratuite (code cadeau couvrant tout le panier).
     */
    public function free(): static
    {
        return $this->state(fn () => [
            'subtotal' => 20.00,
            'discount_amount' => 20.00,
            'shipping_fee' => 0,
            'total' => 0,
        ]);
    }
}
