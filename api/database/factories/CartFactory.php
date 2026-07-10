<?php

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 *
 * Par défaut : panier invité actif (identifié par session_id).
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function converted(): static
    {
        return $this->state(fn () => ['status' => 'converted']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }

    public function withEmail(string $email): static
    {
        return $this->state(fn () => ['guest_email' => $email]);
    }
}
