<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory, HasUuids;

    // Product types with their prices
    public const PRODUCT_TYPES = [
        'digital' => [
            'label' => 'Fichier numérique',
            'price' => 13.00,
            'is_print' => false,
        ],
        'print_10x15' => [
            'label' => 'Tirage 10x15 cm',
            'price' => 10.00,
            'is_print' => true,
        ],
        'print_15x20' => [
            'label' => 'Tirage 15x20 cm',
            'price' => 15.00,
            'is_print' => true,
        ],
        'print_scolaire' => [
            'label' => 'Tirage scolaire',
            'price' => 6.00,
            'is_print' => true,
        ],
    ];

    protected $fillable = [
        'cart_id',
        'photo_id',
        'product_type',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /**
     * Get price for a product type
     */
    public static function getPriceForType(string $productType): float
    {
        return self::PRODUCT_TYPES[$productType]['price'] ?? self::PRODUCT_TYPES['digital']['price'];
    }

    /**
     * Get label for a product type
     */
    public static function getLabelForType(string $productType): string
    {
        return self::PRODUCT_TYPES[$productType]['label'] ?? 'Fichier numérique';
    }

    /**
     * Check if product type is a print
     */
    public static function isPrintType(string $productType): bool
    {
        return self::PRODUCT_TYPES[$productType]['is_print'] ?? false;
    }

    /**
     * Check if this item is a print
     */
    public function isPrint(): bool
    {
        return self::isPrintType($this->product_type);
    }
}
