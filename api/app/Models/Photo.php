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
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_video' => 'boolean',
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
}
