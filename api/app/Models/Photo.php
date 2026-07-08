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
        'file_path_thumbnail_clean',
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
     * Get preview URL (1200px + watermark).
     */
    public function getPreviewUrlAttribute(): ?string
    {
        // For videos, return the original URL
        if ($this->is_video) {
            return $this->getVideoUrl();
        }

        return $this->resolveImageUrl('preview', $this->file_path_preview);
    }

    /**
     * Get thumbnail URL (400px + strong watermark).
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        // For videos, return the original URL
        if ($this->is_video) {
            return $this->getVideoUrl();
        }

        return $this->resolveImageUrl('thumbnail', $this->file_path_thumbnail);
    }

    /**
     * URL de la miniature CLEAN (sans filigrane) pour la grille des galeries
     * téléchargeables.
     *
     * Non appended globalement (cf. $appends) : exposé uniquement par l'endpoint
     * de la galerie de téléchargement, jamais dans les galeries shop — sinon le
     * dérivé sans filigrane fuiterait.
     *
     * Chemin rapide : si la miniature clean a été pré-générée sur MinIO, on sert
     * une URL signée directe (comme les dérivés watermarkés). Fallback sur le
     * proxy /api/images/clean-thumb (génération à la volée, token requis) tant que
     * la miniature n'a pas encore été générée (avant backfill / job asynchrone).
     */
    public function getCleanThumbnailUrlAttribute(): ?string
    {
        if ($this->is_video) {
            return $this->getVideoUrl();
        }

        if (
            config('shop.serve_images_direct', true)
            && $this->is_processed
            && $this->file_path_thumbnail_clean
        ) {
            $signed = app(MinioStorageService::class)->getSignedUrl($this->file_path_thumbnail_clean, 86400);
            if ($signed) {
                return $signed;
            }
        }

        $proxyUrl = url("/api/images/clean-thumb/{$this->id}");
        $token = $this->gallery?->access_token;

        return $token ? "{$proxyUrl}?token={$token}" : $proxyUrl;
    }

    /**
     * Chemin de stockage MinIO déterministe de la miniature clean, dérivé du même
     * basename que la miniature watermarkée (idempotent : le backfill ne crée pas
     * de doublons).
     */
    public function cleanThumbnailStoragePath(): string
    {
        $source = $this->file_path_thumbnail ?: $this->resolved_storage_path;

        return $this->gallery_id.'/thumbnail-clean/'.basename($source);
    }

    /**
     * Résout l'URL d'un dérivé (preview/thumbnail).
     *
     * Mode direct (défaut, cf. config shop.serve_images_direct) : les dérivés DÉJÀ
     * traités et watermarkés sont servis directement depuis MinIO via une URL signée.
     * Le navigateur tire les octets de MinIO, sans passer par PHP-FPM ni le tunnel de
     * l'API — c'est ce qui décharge le backend du streaming sur les grosses galeries.
     *
     * L'URL signée est mise en cache (12 h) pour rester STABLE sur une fenêtre plus
     * courte que sa validité (24 h) : elle reste donc cacheable côté navigateur entre
     * deux visites, avec une marge de 12 h avant expiration.
     *
     * Fallback sur le proxy /api/images/... (génération à la volée + streaming) quand
     * la photo n'est pas encore traitée, si la signature échoue, ou si le mode direct
     * est désactivé.
     */
    private function resolveImageUrl(string $version, ?string $processedPath): ?string
    {
        $proxyUrl = url("/api/images/{$version}/{$this->id}");

        if (! config('shop.serve_images_direct', true)) {
            return $proxyUrl;
        }

        if (! $this->is_processed || ! $processedPath) {
            return $proxyUrl;
        }

        // Génération directe : la signature est du pur calcul (HMAC local, aucune I/O).
        // PAS de cache ici — un cache fichier ajoutait 400 accès au disque du NAS (2 par
        // photo × 200) à la réponse JSON, soit ~19 s de latence. Conséquence : l'URL
        // change à chaque chargement (pas de cache navigateur entre 2 visites) ; c'est
        // un compromis assumé tant qu'on n'a pas de cache rapide (Redis) ou de signature
        // déterministe. Voir aussi le mécanisme vidéo dans getVideoUrl().
        return app(MinioStorageService::class)->getSignedUrl($processedPath, 86400) ?: $proxyUrl;
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
