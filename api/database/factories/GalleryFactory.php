<?php

namespace Database\Factories;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 *
 * Par défaut : galerie privée (access_token + share_code générés par le boot du modèle).
 */
class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => 'private',
            'views_count' => 0,
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['type' => 'public']);
    }

    public function private(): static
    {
        return $this->state(fn () => ['type' => 'private']);
    }

    /**
     * Galerie de type "événement". Publiée par défaut (visible publiquement).
     */
    public function event(): static
    {
        return $this->state(fn () => [
            'type' => 'event',
            'is_published' => true,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    /**
     * Frais de port spécifiques à la galerie (tirages).
     */
    public function withShippingFee(float $fee): static
    {
        return $this->state(fn () => ['shipping_fee' => $fee]);
    }
}
