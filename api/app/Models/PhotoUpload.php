<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     *
     * @param  bool  $includeUploads  Whether to include the full uploads list (heavy for 1000+ items).
     *                                Set to false for school session polling where only counts are needed.
     */
    public static function getBatchStatus(string $batchId, bool $includeUploads = true): array
    {
        // Aggregate counts via COUNT queries (no row hydration)
        $counts = self::where('batch_id', $batchId)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'completed') as completed")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'failed') as failed")
            ->selectRaw("COUNT(*) FILTER (WHERE status IN ('pending', 'uploading', 'processing')) as processing")
            ->first();

        if (! $counts || $counts->total === 0) {
            return [
                'batch_id' => $batchId,
                'found' => false,
                'uploads' => [],
            ];
        }

        $total = (int) $counts->total;
        $completed = (int) $counts->completed;
        $failed = (int) $counts->failed;
        $processing = (int) $counts->processing;

        $result = [
            'batch_id' => $batchId,
            'found' => true,
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'processing' => $processing,
            'progress' => $total > 0 ? round((($completed + $failed) / $total) * 100) : 0,
            'is_complete' => $processing === 0,
        ];

        if ($includeUploads) {
            $result['uploads'] = self::where('batch_id', $batchId)
                ->get()
                ->map(fn ($upload) => [
                    'id' => $upload->id,
                    'original_filename' => $upload->original_filename,
                    'status' => $upload->status,
                    'error_message' => $upload->error_message,
                    'photo_id' => $upload->photo_id,
                ])
                ->toArray();
        }

        return $result;
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
