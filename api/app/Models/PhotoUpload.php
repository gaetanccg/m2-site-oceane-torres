<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PhotoUpload extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_id',
        'gallery_id',
        'original_filename',
        'status',
        'error_message',
        'photo_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /**
     * Get the status of all uploads in a batch
     */
    public static function getBatchStatus(string $batchId): array
    {
        $uploads = self::where('batch_id', $batchId)->get();

        if ($uploads->isEmpty()) {
            return [
                'batch_id' => $batchId,
                'found' => false,
                'uploads' => [],
            ];
        }

        $total = $uploads->count();
        $completed = $uploads->where('status', 'completed')->count();
        $failed = $uploads->where('status', 'failed')->count();
        $processing = $uploads->whereIn('status', ['pending', 'uploading', 'processing'])->count();

        return [
            'batch_id' => $batchId,
            'found' => true,
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'processing' => $processing,
            'progress' => $total > 0 ? round((($completed + $failed) / $total) * 100) : 0,
            'is_complete' => $processing === 0,
            'uploads' => $uploads->map(fn ($upload) => [
                'id' => $upload->id,
                'original_filename' => $upload->original_filename,
                'status' => $upload->status,
                'error_message' => $upload->error_message,
                'photo_id' => $upload->photo_id,
            ])->toArray(),
        ];
    }

    /**
     * Mark upload as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark upload as completed with the created photo
     */
    public function markAsCompleted(string $photoId): void
    {
        $this->update([
            'status' => 'completed',
            'photo_id' => $photoId,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark upload as failed with error message
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}
