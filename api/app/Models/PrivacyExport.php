<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Export RGPD (global ou ciblé) généré de façon asynchrone : un ZIP contenant
 * un JSON par table + les PDF de factures. Suit le même patron que
 * SchoolSessionExport (statut + progression + fichier sur le disque local).
 */
class PrivacyExport extends Model
{
    use HasFactory, HasUuids;

    const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    protected $fillable = [
        'type',
        'subject_type',
        'subject_value',
        'status',
        'total_items',
        'processed_items',
        'file_path',
        'file_size_bytes',
        'error_message',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed';
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
