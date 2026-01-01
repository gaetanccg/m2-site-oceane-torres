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
        'title',
        'description',
        'type',
        'access_token',
        'share_code',
        'expiration_at',
        'cover_image',
        'is_active',
        'last_viewed_at',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'expiration_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'is_active' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gallery) {
            // All galleries get both access_token and share_code
            if (empty($gallery->access_token)) {
                $gallery->access_token = Str::random(64);
            }
            if (empty($gallery->share_code)) {
                $gallery->share_code = self::generateUniqueShareCode();
            }
            // Default type to private
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
        return $this->hasMany(Photo::class);
    }

    public function isExpired(): bool
    {
        if ($this->expiration_at === null) {
            return false;
        }
        return $this->expiration_at->isPast();
    }

    public function isAccessible(?string $token = null): bool
    {
        if ($this->type === 'public') {
            return true;
        }

        if ($this->isExpired()) {
            return false;
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    public function getDownloadablePhotosCountAttribute(): int
    {
        return $this->photos()->where('is_downloadable', true)->count();
    }

    public function getCoverImageAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        $firstPhoto = $this->photos()->ordered()->first();
        return $firstPhoto?->file_path;
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
}
