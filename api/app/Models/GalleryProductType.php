<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalleryProductType extends Model
{
    use CastsBooleansForPostgres, HasUuids;

    protected $fillable = [
        'gallery_id',
        'product_type',
        'is_enabled',
        'price',
        'requires_shipping',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'price' => 'decimal:2',
            'requires_shipping' => 'boolean',
        ];
    }

    /**
     * Returns the effective shipping rule: explicit override (true/false) or static fallback.
     */
    public function effectiveRequiresShipping(): bool
    {
        return $this->requires_shipping ?? CartItem::requiresShipping($this->product_type);
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function packTiers(): HasMany
    {
        return $this->hasMany(PackTier::class)->orderBy('min_quantity');
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

    /**
     * Deterministic signature for cross-gallery pack grouping.
     * Two GPTs with the same signature represent the same offer and can cumulate quantities.
     * Returns null if disabled or has no pack tiers (no cumulation possible).
     */
    public function offerSignature(): ?string
    {
        $this->loadMissing('packTiers');

        if (! $this->is_enabled || $this->packTiers->isEmpty()) {
            return null;
        }

        $tiers = $this->packTiers
            ->sortBy('min_quantity')
            ->map(fn ($t) => $t->min_quantity.':'.$t->unit_price)
            ->implode('|');

        return md5($this->product_type.'/'.$this->effective_price.'/'.$tiers);
    }
}
