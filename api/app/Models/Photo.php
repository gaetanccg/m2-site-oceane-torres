<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use App\Services\MinioStorageService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Photo extends Model
{
    use CastsBooleansForPostgres, HasFactory, HasUuids;

    protected static function booted(): void
    {
        // Invalidate ImageProxyController metadata cache when photo changes
        // (upload processing flips is_processed, admin edits title/price, etc.).
        static::saved(fn (self $photo) => Cache::forget("photo_meta_{$photo->id}"));
        static::deleted(fn (self $photo) => Cache::forget("photo_meta_{$photo->id}"));
    }

    protected $fillable = [
        'gallery_id',
        'file_path',
        'file_path_web',
        'file_path_hd',
        'file_path_watermark',
        'file_path_preview',
        'file_path_thumbnail',
        'is_processed',
        'is_video',
        'title',
        'description',
        'sort_order',
        'is_liked',
        'is_downloadable',
        'price',
        'is_purchasable',
        'downloads_count',
        'metadata',
    ];

    protected $appends = ['display_url', 'preview_url', 'thumbnail_url'];

    protected function casts(): array
    {
        return [
            'is_video' => 'boolean',
            'is_liked' => 'boolean',
            'is_downloadable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_processed' => 'boolean',
            'price' => 'decimal:2',
            'downloads_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get display URL (kept for backwards compatibility)
     *
     * @deprecated Use preview_url or thumbnail_url instead
     */
    public function getDisplayUrlAttribute(): ?string
    {
        // Return preview URL for backwards compatibility
        return $this->preview_url;
    }

    /**
     * Get preview URL (1200px + watermark) via proxy
     */
    public function getPreviewUrlAttribute(): ?string
    {
        // For videos, return the original URL
        if ($this->is_video) {
            return $this->getVideoUrl();
        }

        // Return proxy URL
        return url("/api/images/preview/{$this->id}");
    }

    /**
     * Get thumbnail URL (400px + strong watermark) via proxy
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        // For videos, return the original URL
        if ($this->is_video) {
            return $this->getVideoUrl();
        }

        // Return proxy URL
        return url("/api/images/thumbnail/{$this->id}");
    }

    /**
     * Get video URL (videos are not proxied)
     */
    private function getVideoUrl(): ?string
    {
        $storagePath = $this->resolved_storage_path;

        // If it's already a full URL (legacy Supabase), return as-is
        if (str_starts_with($storagePath, 'http')) {
            return $storagePath;
        }

        // Generate signed URL from MinIO
        try {
            $storageService = app(MinioStorageService::class);

            return $storageService->getSignedUrl($storagePath, 3600);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve the storage path for the original/HD version of this photo.
     * Centralizes the fallback chain used across controllers and services.
     */
    public function getResolvedStoragePathAttribute(): string
    {
        return $this->file_path_hd
            ?? $this->metadata['storage_path']
            ?? $this->metadata['supabase_path']
            ?? $this->file_path;
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function getWebUrlAttribute(): ?string
    {
        return $this->file_path_web ?? $this->file_path;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->file_path_hd ?? $this->file_path;
    }

    public function scopeImages($query)
    {
        return $query->whereRaw('is_video = false');
    }

    public function scopeVideos($query)
    {
        return $query->whereRaw('is_video = true');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeDownloadable($query)
    {
        return $query->whereRaw('is_downloadable = true');
    }

    public function scopeLiked($query)
    {
        return $query->whereRaw('is_liked = true');
    }

    public function scopePurchasable($query)
    {
        return $query->whereRaw('is_purchasable = true');
    }

    public function getEffectivePriceAttribute(): float
    {
        // Prix par défaut si la photo n'a pas de prix défini
        // Les prix réels sont définis dans CartItem::PRODUCT_TYPES
        return $this->price ?? 13.00;
    }

    public function toggleLike(): bool
    {
        $this->is_liked = ! $this->is_liked;
        $this->save();

        return $this->is_liked;
    }

    public function toggleDownloadable(): bool
    {
        $this->is_downloadable = ! $this->is_downloadable;
        $this->save();

        return $this->is_downloadable;
    }

    public function downloadLogs(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function recordDownload(?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->increment('downloads_count');

        $this->downloadLogs()->create([
            'gallery_id' => $this->gallery_id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            'downloaded_at' => now(),
        ]);
    }
}
