<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilitySlot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'status',
        'reservation_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
        ];
    }

    // Relations

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    // Scopes

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeBooked($query)
    {
        return $query->where('status', 'booked');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now()->toDateString());
    }

    // Helpers

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isBooked(): bool
    {
        return $this->status === 'booked';
    }

    public function markAsBooked(Reservation $reservation): void
    {
        $this->update([
            'status' => 'booked',
            'reservation_id' => $reservation->id,
        ]);
    }

    public function release(): void
    {
        $this->update([
            'status' => 'available',
            'reservation_id' => null,
        ]);
    }

    public function getFormattedTimeRangeAttribute(): string
    {
        $start = \Carbon\Carbon::parse($this->start_time)->format('H:i');
        $end = \Carbon\Carbon::parse($this->end_time)->format('H:i');

        return "{$start} - {$end}";
    }
}
