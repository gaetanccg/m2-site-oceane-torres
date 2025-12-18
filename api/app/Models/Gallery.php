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
        'expiration_at',
        'cover_image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expiration_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gallery) {
            if ($gallery->type === 'private' && empty($gallery->access_token)) {
                $gallery->access_token = Str::random(64);
            }
        });
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
}
