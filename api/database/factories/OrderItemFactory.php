<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 *
 * Par défaut : article numérique (digital), non téléchargé.
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'photo_id' => Photo::factory(),
            'product_type' => 'digital',
            'quantity' => 1,
            'photo_title' => fake()->words(2, true),
            'gallery_title' => fake()->words(3, true),
            'price' => 13.00,
            'is_downloaded' => false,
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

    public function downloaded(): static
    {
        return $this->state(fn () => [
            'is_downloaded' => true,
            'downloaded_at' => now(),
        ]);
    }

    public function forPhoto(Photo $photo): static
    {
        return $this->state(fn () => [
            'photo_id' => $photo->id,
            'photo_title' => $photo->title,
            'gallery_title' => $photo->gallery?->title,
        ]);
    }
}
