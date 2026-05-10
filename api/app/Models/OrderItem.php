<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use CastsBooleansForPostgres, HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'photo_id',
        'product_type',
        'quantity',
        'photo_title',
        'gallery_title',
        'price',
        'is_downloaded',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'is_downloaded' => 'boolean',
            'downloaded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function markAsDownloaded(): void
    {
        $this->update([
            'is_downloaded' => true,
            'downloaded_at' => now(),
        ]);
    }

    /**
     * Check if this item is a print order
     */
    public function isPrint(): bool
    {
        return CartItem::isPrintType($this->product_type ?? 'digital');
    }

    /**
     * Get product type label
     */
    public function getProductTypeLabel(): string
    {
        return CartItem::getLabelForType($this->product_type ?? 'digital');
    }
}
