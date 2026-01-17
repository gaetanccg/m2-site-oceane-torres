<?php

namespace App\Models;

use App\Services\MinioStorageService;
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
        'is_liked',
        'is_downloadable',
        'metadata',
    ];

    protected $appends = ['display_url'];

    protected function casts(): array
    {
        return [
            'is_video' => 'boolean',
            'is_liked' => 'boolean',
            'is_downloadable' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getDisplayUrlAttribute(): ?string
    {
        $storagePath = $this->metadata['storage_path'] ?? $this->metadata['supabase_path'] ?? $this->file_path;

        // If it's already a full URL (legacy Supabase), return as-is
        if (str_starts_with($storagePath, 'http')) {
            return $storagePath;
        }

        // Generate signed URL from MinIO
        try {
            $storageService = new MinioStorageService();
            return $storageService->getSignedUrl($storagePath, 3600);
        } catch (\Exception $e) {
            return null;
        }
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
        return $query->where('is_liked', true);
    }

    public function toggleLike(): bool
    {
        $this->is_liked = !$this->is_liked;
        $this->save();

        return $this->is_liked;
    }

    public function toggleDownloadable(): bool
    {
        $this->is_downloadable = !$this->is_downloadable;
        $this->save();

        return $this->is_downloadable;
    }
}
