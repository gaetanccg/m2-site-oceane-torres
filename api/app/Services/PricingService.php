<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Gallery;

class PricingService
{
    /**
     * Get available product types for a gallery with resolved prices.
     */
    public function getAvailableProductTypes(Gallery $gallery): array
    {
        $configured = $gallery->galleryProductTypes;

        if ($configured->isEmpty()) {
            $result = [];
            foreach (CartItem::PRODUCT_TYPES as $type => $info) {
                $result[$type] = [
                    'label' => $info['label'],
                    'price' => $info['price'],
                    'is_print' => $info['is_print'],
                    'is_enabled' => true,
                ];
            }

            return $result;
        }

        $result = [];
        foreach (CartItem::PRODUCT_TYPES as $type => $info) {
            $config = $configured->firstWhere('product_type', $type);

            $result[$type] = [
                'label' => $info['label'],
                'price' => $config ? $config->effective_price : $info['price'],
                'is_print' => $info['is_print'],
                'is_enabled' => $config ? $config->is_enabled : false,
            ];
        }

        return $result;
    }

    /**
     * Get pack pricing info for all product types on a gallery.
     */
    public function getPackPricing(Gallery $gallery): array
    {
        $gallery->loadMissing('galleryProductTypes.packTiers');
        $result = [];

        foreach ($gallery->galleryProductTypes as $gpt) {
            if (! $gpt->is_enabled || $gpt->packTiers->isEmpty()) {
                continue;
            }

            $result[$gpt->product_type] = [
                'label' => CartItem::PRODUCT_TYPES[$gpt->product_type]['label'] ?? $gpt->product_type,
                'base_price' => $gpt->effective_price,
                'tiers' => $gpt->packTiers->map(fn ($t) => [
                    'min_quantity' => $t->min_quantity,
                    'unit_price' => (float) $t->unit_price,
                ])->values()->toArray(),
            ];
        }

        return $result;
    }

    /**
     * Resolve the unit price for a product type given a quantity.
     */
    public function resolvePackPrice(Gallery $gallery, string $productType, int $quantity): ?float
    {
        $gallery->loadMissing('galleryProductTypes.packTiers');
        $gpt = $gallery->galleryProductTypes->firstWhere('product_type', $productType);

        if (! $gpt || ! $gpt->is_enabled) {
            return null;
        }

        $matchingTier = $gpt->packTiers
            ->where('min_quantity', '<=', $quantity)
            ->sortByDesc('min_quantity')
            ->first();

        return $matchingTier ? (float) $matchingTier->unit_price : $gpt->effective_price;
    }

    /**
     * Get the price for a specific product type on a gallery.
     */
    public function getPriceForProductType(Gallery $gallery, string $productType): ?float
    {
        $configured = $gallery->galleryProductTypes->firstWhere('product_type', $productType);

        if ($gallery->galleryProductTypes->isEmpty()) {
            return CartItem::getPriceForType($productType);
        }

        if (! $configured || ! $configured->is_enabled) {
            return null;
        }

        return $configured->effective_price;
    }
}
