<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackTier extends Model
{
    use HasUuids;

    protected $fillable = ['gallery_product_type_id', 'min_quantity', 'unit_price'];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function galleryProductType(): BelongsTo
    {
        return $this->belongsTo(GalleryProductType::class);
    }
}
