<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSessionContact extends Model
{
    use HasFactory, HasUuids;

    public const STATUSES = ['pending', 'sent', 'failed'];

    protected $fillable = [
        'school_session_id',
        'gallery_id',
        'recipient_name',
        'email',
        'phone',
        'channel',
        'status',
        'sent_at',
        'error_message',
        'send_attempts',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'send_attempts' => 'integer',
        ];
    }

    public function schoolSession(): BelongsTo
    {
        return $this->belongsTo(SchoolSession::class);
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function markSent(string $channel): void
    {
        $this->update([
            'channel' => $channel,
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
            'send_attempts' => $this->send_attempts + 1,
        ]);
    }

    public function markFailed(string $channel, string $raison): void
    {
        $this->update([
            'channel' => $channel,
            'status' => 'failed',
            'error_message' => $raison,
            'send_attempts' => $this->send_attempts + 1,
        ]);
    }

    public function scopeNotSent($query)
    {
        return $query->whereIn('status', ['pending', 'failed']);
    }
}
