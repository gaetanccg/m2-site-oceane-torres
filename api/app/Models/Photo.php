<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'gallery_id',
        'file_path',
        'file_path_web',
        'file_path_hd',
        'file_path_watermark',
        'is_video',
        'title',
        'description',
        'sort_order',
        'likes_count',
        'is_downloadable',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_video' => 'boolean',
            'is_downloadable' => 'boolean',
            'metadata' => 'array',
        ];
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
        return $query->where('is_video', false);
    }

    public function scopeVideos($query)
    {
        return $query->where('is_video', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeDownloadable($query)
    {
        return $query->where('is_downloadable', true);
    }

    public function scopeLiked($query)
    {
        return $query->where('likes_count', '>', 0);
    }

    public function incrementLikes(): void
    {
        $this->increment('likes_count');
    }

    public function toggleDownloadable(): bool
    {
        $this->is_downloadable = !$this->is_downloadable;
        $this->save();

        return $this->is_downloadable;
    }
}
