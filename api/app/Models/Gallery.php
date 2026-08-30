<?php

namespace App\Models;

use App\Services\PricingService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
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
        'kind',
        'access_token',
        'share_code',
        'sms_template',
        'last_viewed_at',
        'views_count',
        'event_category_id',
        'parent_id',
        'sort_order',
        'is_published',
        'school_session_id',
        'class_name',
        'shipping_fee',
    ];

    protected $appends = [];

    /**
     * `access_token` ouvre la galerie telechargeable, donc les photos sans filigrane :
     * il ne doit jamais sortir sur un endpoint public. Masques par defaut pour qu'un
     * nouvel endpoint ne les expose pas par oubli — voir withPrivateFields().
     */
    protected $hidden = [
        'access_token',
        'assigned_email',
        'sms_template',
    ];

    /** Administration et proprietaire uniquement. */
    public function withPrivateFields(): static
    {
        return $this->makeVisible($this->getHidden());
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'last_viewed_at' => 'datetime',
            'views_count' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'shipping_fee' => 'decimal:2',
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
            if (empty($gallery->kind)) {
                $gallery->kind = self::natureDeduite($gallery);
            }
        });
    }

    /**
     * La clef etrangere que portent les relations de ce modele, posee explicitement.
     *
     * Sans cela, Eloquent la derive du NOM DE CLASSE : `EventGallery::photos()` cherchait
     * `photos.event_gallery_id`, colonne qui n'existe pas, et l'endpoint public des
     * evenements repondait 500. C'est le piege de l'heritage a table unique, et il ne se voit
     * qu'a l'execution — le typage ne l'attrape pas.
     *
     * Une seule declaration ici sert les trois sous-classes.
     */
    public function getForeignKey(): string
    {
        return 'gallery_id';
    }

    /** Les trois natures de galerie. Une quatrieme exigerait une decision, pas un ajout. */
    public const KIND_CLIENT = 'client';

    public const KIND_EVENT = 'event';

    public const KIND_SCHOOL = 'school';

    public const KINDS = [self::KIND_CLIENT, self::KIND_EVENT, self::KIND_SCHOOL];

    /**
     * La nature d'une galerie qui n'en declare pas, deduite de son contexte.
     *
     * C'est la MEME regle que le backfill de `2026_08_31_100000`, et l'ordre y compte autant :
     * une galerie scolaire porte `type = 'private'`, donc le scolaire se teste avant le type.
     * Ce repli existe pour que le code ancien continue de creer des galeries correctes ; le
     * code neuf devrait poser `kind` explicitement.
     */
    private static function natureDeduite(self $gallery): string
    {
        if (! empty($gallery->school_session_id)) {
            return self::KIND_SCHOOL;
        }

        if ($gallery->type === 'event') {
            return self::KIND_EVENT;
        }

        return self::KIND_CLIENT;
    }

    protected static function booted(): void
    {
        static::saved(function (self $gallery) {
            if ($gallery->wasChanged(['share_code', 'access_token'])) {
                $gallery->forgetPhotoMetaCache();
            }
        });
    }

    /**
     * ImageProxyController met en cache la photo AVEC sa galerie : sans cette
     * invalidation, l'ancien credential resterait valable jusqu'a l'expiration du cache.
     */
    public function forgetPhotoMetaCache(): void
    {
        foreach ($this->photos()->pluck('id') as $photoId) {
            Cache::forget("photo_meta_{$photoId}");
        }
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

    public function eventCategory(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class);
    }

    public function schoolSession(): BelongsTo
    {
        return $this->belongsTo(SchoolSession::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Gallery::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
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
        return $this->holdsAccessToken($token);
    }

    /**
     * Exigence renforcee pour les derives SANS filigrane. `isAccessible()` ne convient
     * pas : elle rend true sans jeton pour une galerie `public`, ce qui livrerait les
     * originaux propres — precisement ce que le filigranage protege.
     */
    public function grantsCleanDerivatives(?string $token = null): bool
    {
        return $this->holdsAccessToken($token);
    }

    /**
     * Derives FILIGRANES. Le code de partage suffit — c'est le credential que detient la
     * visiteuse, et celui dont la regeneration doit revoquer l'acces. Y mettre
     * `access_token` rouvrirait l'escalade vers les derives propres : une URL est une
     * chaine, que $hidden ne filtre pas.
     */
    public function grantsWatermarkedPreview(?string $credential = null): bool
    {
        if ($this->kind === self::KIND_EVENT && $this->is_published) {
            return true;
        }

        return $this->holdsAccessToken($credential) || $this->holdsShareCode($credential);
    }

    private function holdsAccessToken(?string $token): bool
    {
        return $token !== null && $token !== '' && hash_equals($this->access_token, $token);
    }

    private function holdsShareCode(?string $candidate): bool
    {
        return $candidate !== null && $candidate !== '' && hash_equals((string) $this->share_code, $candidate);
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
        return $this->photos()->where('is_liked', true)->count();
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
        return $this->photos()->where('is_downloadable', true)->count();
    }

    public function getLikedPhotosCountAttribute(): int
    {
        return $this->photos()->where('is_liked', true)->count();
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
     * Delegate pricing methods to PricingService.
     * Kept as proxy methods to avoid breaking existing callers.
     */
    public function getAvailableProductTypes(): array
    {
        return app(PricingService::class)->getAvailableProductTypes($this);
    }

    public function getPackPricing(): array
    {
        return app(PricingService::class)->getPackPricing($this);
    }

    public function resolvePackPrice(string $productType, int $quantity): ?float
    {
        return app(PricingService::class)->resolvePackPrice($this, $productType, $quantity);
    }

    public function getPriceForProductType(string $productType): ?float
    {
        return app(PricingService::class)->getPriceForProductType($this, $productType);
    }

    /**
     * Resolve whether a product type triggers shipping for this gallery.
     * Per-gallery override on GalleryProductType takes precedence; fallback to static rule.
     */
    public function getRequiresShippingForProductType(string $productType): bool
    {
        $gpt = $this->galleryProductTypes->firstWhere('product_type', $productType);

        return $gpt
            ? $gpt->effectiveRequiresShipping()
            : CartItem::requiresShipping($productType);
    }

    /**
     * Per-gallery shipping fee amount, falling back to the global config default
     * when no explicit value is set on the gallery.
     */
    public function getShippingFee(): float
    {
        return (float) ($this->shipping_fee ?? config('shop.shipping_fee_print', 0));
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
        $downloadableCount = $this->photos()->where('is_downloadable', true)->count();

        if ($downloadableCount === 0) {
            return 'none';
        }

        $downloadedCount = $this->photos()
            ->where('is_downloadable', true)
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
