<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Prestation extends Model
{
    /** La cle du catalogue public. Une seule declaration, lecteur comme ecrivains. */
    public const CACHE_PUBLIC = 'prestations_public';

    /**
     * Ce qui change une prestation rafraichit le catalogue public.
     *
     * L'invalidation etait recopiee dans les quatre methodes d'administration qui ecrivent.
     * Recopier une invalidation, c'est se donner rendez-vous avec celle qu'on oubliera — trois
     * chemins de photos l'avaient deja prouve sur la liste des evenements.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_PUBLIC));
        static::deleted(fn () => Cache::forget(self::CACHE_PUBLIC));
    }

    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'icon',
        'description',
        'features',
        'price',
        'price_text',
        'price_unit',
        'duration',
        'category',
        'background_image',
        'background_opacity',
        'disclaimer',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'background_opacity' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
