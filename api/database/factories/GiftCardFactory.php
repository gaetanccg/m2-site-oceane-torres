<?php

namespace Database\Factories;

use App\Models\GiftCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftCard>
 *
 * Par défaut : bon de 100 € intact, actif, valable un an — le cas nominal.
 */
class GiftCardFactory extends Factory
{
    protected $model = GiftCard::class;

    public function definition(): array
    {
        return [
            'amount' => 100.00,
            'remaining_amount' => 100.00,
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->safeEmail(),
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ];
    }

    public function partiallyUsed(float $remaining): static
    {
        return $this->state(fn () => ['remaining_amount' => $remaining]);
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'remaining_amount' => 0,
            'status' => 'used',
            'used_at' => now(),
        ]);
    }

    /** Le statut reste 'active' : l'expiration se lit sur la date, pas sur le statut. */
    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function neverExpires(): static
    {
        return $this->state(fn () => ['expires_at' => null]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }
}
