<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 *
 * Par défaut : article numérique (digital), quantité 1.
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'photo_id' => Photo::factory(),
            'product_type' => 'digital',
            'quantity' => 1,
            'price' => 13.00,
        ];
    }

    public function digital(): static
    {
        return $this->state(fn () => ['product_type' => 'digital', 'price' => 13.00]);
    }

    public function print(string $type = 'print_10x15'): static
    {
        return $this->state(fn () => [
            'product_type' => $type,
            'price' => CartItem::getPriceForType($type),
        ]);
    }

    public function quantity(int $qty): static
    {
        return $this->state(fn () => ['quantity' => $qty]);
    }

    public function forPhoto(Photo $photo): static
    {
        return $this->state(fn () => ['photo_id' => $photo->id]);
    }
}
