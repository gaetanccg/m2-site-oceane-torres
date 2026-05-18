<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolSession extends Model
{
    use HasUuids;

    const STATUSES = [
        'uploading',
        'extracting',
        'creating_galleries',
        'processing_photos',
        'completed',
        'failed',
    ];

    protected $fillable = [
        'title',
        'event_date',
        'status',
        'total_galleries',
        'total_photos',
        'processed_photos',
        'batch_id',
        'zip_path',
        'error_message',
        'product_types_config',
        'gallery_message',
        'sms_template',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'total_galleries' => 'integer',
            'total_photos' => 'integer',
            'processed_photos' => 'integer',
            'product_types_config' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class)->orderBy('title');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(SchoolSessionExport::class);
    }

    /**
     * Compute live progress from PhotoUpload batch.
     */
    public function getProgressAttribute(): ?array
    {
        if (! $this->batch_id) {
            return null;
        }

        return PhotoUpload::getBatchStatus($this->batch_id);
    }

    public function markAs(string $status, ?string $error = null): void
    {
        $data = ['status' => $status];
        if ($error !== null) {
            $data['error_message'] = $error;
        }
        $this->update($data);
    }
}
