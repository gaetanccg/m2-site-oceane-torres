<?php

namespace App\Traits;

use App\Models\Gallery;
use App\Models\GalleryProductType;
use App\Models\PackTier;

trait SyncsProductTypes
{
    protected function syncProductTypes(Gallery $gallery, array $productTypes): void
    {
        $gallery->galleryProductTypes()->delete();

        foreach ($productTypes as $config) {
            $gpt = GalleryProductType::create([
                'gallery_id' => $gallery->id,
                'product_type' => $config['product_type'],
                'is_enabled' => $config['is_enabled'],
                'price' => $config['price'] ?? null,
                'requires_shipping' => array_key_exists('requires_shipping', $config) ? $config['requires_shipping'] : null,
            ]);

            if (! empty($config['tiers'])) {
                foreach ($config['tiers'] as $tier) {
                    PackTier::create([
                        'gallery_product_type_id' => $gpt->id,
                        'min_quantity' => $tier['min_quantity'],
                        'unit_price' => $tier['unit_price'],
                    ]);
                }
            }
        }
    }

    protected function productTypeValidationRules(): array
    {
        return [
            'product_types' => ['nullable', 'array'],
            'product_types.*.product_type' => ['required_with:product_types', 'string', 'in:digital,print_10x15,print_15x20,print_scolaire'],
            'product_types.*.is_enabled' => ['required_with:product_types', 'boolean'],
            'product_types.*.price' => ['nullable', 'numeric', 'min:0.01'],
            'product_types.*.requires_shipping' => ['nullable', 'boolean'],
            'product_types.*.tiers' => ['nullable', 'array', 'max:3'],
            'product_types.*.tiers.*.min_quantity' => ['required', 'integer', 'min:2'],
            'product_types.*.tiers.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
