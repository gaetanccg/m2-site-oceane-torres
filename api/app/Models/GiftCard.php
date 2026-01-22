<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GiftCard extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payment_id',
        'code',
        'amount',
        'remaining_amount',
        'recipient_name',
        'recipient_email',
        'message',
        'pdf_path',
        'status',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($giftCard) {
            if (empty($giftCard->code)) {
                $giftCard->code = self::generateUniqueCode();
            }
            if ($giftCard->remaining_amount === null) {
                $giftCard->remaining_amount = $giftCard->amount;
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(12));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->remaining_amount > 0;
    }

    public function use(float $amount): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        if ($amount > $this->remaining_amount) {
            return false;
        }

        $this->remaining_amount -= $amount;

        if ($this->remaining_amount <= 0) {
            $this->status = 'used';
            $this->used_at = now();
        }

        return $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where('remaining_amount', '>', 0);
    }
}
