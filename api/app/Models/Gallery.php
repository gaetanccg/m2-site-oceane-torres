<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Gallery extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'assigned_email',
        'title',
        'description',
        'event_date',
        'event_link',
        'thumbnail_photo_id',
        'type',
        'access_token',
        'share_code',
        'last_viewed_at',
        'views_count',
    ];

    protected $appends = [
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'last_viewed_at' => 'datetime',
            'views_count' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gallery) {
            if (empty($gallery->access_token)) {
                $gallery->access_token = Str::random(64);
            }
            if (empty($gallery->share_code)) {
                $gallery->share_code = self::generateUniqueShareCode();
            }
            if (empty($gallery->type)) {
                $gallery->type = 'private';
            }
        });
    }

    public static function generateUniqueShareCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('share_code', $code)->exists());

        return $code;
    }

    public function regenerateShareCode(): string
    {
        $this->share_code = self::generateUniqueShareCode();
        $this->save();

        return $this->share_code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('title');
    }

    /**
     * Get the selected thumbnail photo for this gallery
     */
    public function thumbnailPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'thumbnail_photo_id');
    }

    /**
     * Get the cover photo (selected thumbnail or first photo)
     */
    public function getCoverPhotoAttribute(): ?Photo
    {
        return $this->thumbnailPhoto ?? $this->photos()->first();
    }

    public function isAccessible(?string $token = null): bool
    {
        if ($this->type === 'public') {
            return true;
        }

        return $token === $this->access_token;
    }

    public function scopePublic($query)
    {
        return $query->where('type', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    public function scopeByShareCode($query, string $code)
    {
        return $query->where('share_code', strtoupper($code));
    }

    public function scopeByAccessToken($query, string $token)
    {
        return $query->where('access_token', $token);
    }

    public function getTotalLikesAttribute(): int
    {
        return $this->photos()->whereRaw('is_liked = true')->count();
    }

    /**
     * Get the client_id (from clients table) based on user_id
     */
    public function getClientIdAttribute(): ?string
    {
        if (! $this->user_id) {
            return null;
        }

        return Client::where('user_id', $this->user_id)->value('id');
    }

    public function getDownloadablePhotosCountAttribute(): int
    {
        return $this->photos()->whereRaw('is_downloadable = true')->count();
    }

    public function getLikedPhotosCountAttribute(): int
    {
        return $this->photos()->whereRaw('is_liked = true')->count();
    }

    public function recordView(): void
    {
        $this->increment('views_count');
        $this->update(['last_viewed_at' => now()]);
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function galleryProductTypes(): HasMany
    {
        return $this->hasMany(GalleryProductType::class);
    }

    /**
     * Get available product types for this gallery with resolved prices.
     * If no configuration exists, all product types are available at default prices.
     */
    public function getAvailableProductTypes(): array
    {
        $configured = $this->galleryProductTypes;

        // No configuration → all types at default prices
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

        // Build from configuration
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
     * Get the price for a specific product type on this gallery.
     * Returns null if the product type is disabled.
     */
    public function getPriceForProductType(string $productType): ?float
    {
        $configured = $this->galleryProductTypes->firstWhere('product_type', $productType);

        // No configuration for this gallery → default price
        if ($this->galleryProductTypes->isEmpty()) {
            return CartItem::getPriceForType($productType);
        }

        // Product type not configured or disabled
        if (! $configured || ! $configured->is_enabled) {
            return null;
        }

        return $configured->effective_price;
    }

    /**
     * Get total downloads count across all photos
     */
    public function getTotalDownloadsCountAttribute(): int
    {
        return $this->photos()->sum('downloads_count');
    }

    /**
     * Get count of photos that have been downloaded at least once
     */
    public function getDownloadedPhotosCountAttribute(): int
    {
        return $this->photos()->where('downloads_count', '>', 0)->count();
    }

    /**
     * Get download status: 'none', 'partial', 'complete'
     * - none: no downloadable photos have been downloaded
     * - partial: some downloadable photos have been downloaded
     * - complete: all downloadable photos have been downloaded at least once
     */
    public function getDownloadStatusAttribute(): string
    {
        $downloadableCount = $this->photos()->whereRaw('is_downloadable = true')->count();

        if ($downloadableCount === 0) {
            return 'none';
        }

        $downloadedCount = $this->photos()
            ->whereRaw('is_downloadable = true')
            ->where('downloads_count', '>', 0)
            ->count();

        if ($downloadedCount === 0) {
            return 'none';
        }

        if ($downloadedCount >= $downloadableCount) {
            return 'complete';
        }

        return 'partial';
    }
}
