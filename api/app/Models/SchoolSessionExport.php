<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSessionExport extends Model
{
    use CastsBooleansForPostgres, HasUuids;

    const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    protected $fillable = [
        'school_session_id',
        'status',
        'include_digital',
        'file_path',
        'file_size_bytes',
        'total_items',
        'processed_items',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'include_digital' => 'boolean',
            'file_size_bytes' => 'integer',
            'total_items' => 'integer',
            'processed_items' => 'integer',
        ];
    }

    public function schoolSession(): BelongsTo
    {
        return $this->belongsTo(SchoolSession::class);
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
