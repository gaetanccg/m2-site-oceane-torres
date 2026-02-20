<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryProductType extends Model
{
    use CastsBooleansForPostgres, HasUuids;

    protected $fillable = [
        'gallery_id',
        'product_type',
        'is_enabled',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the effective price: override or default from CartItem::PRODUCT_TYPES
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->price !== null
            ? (float) $this->price
            : CartItem::getPriceForType($this->product_type);
    }
}
