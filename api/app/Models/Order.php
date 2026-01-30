<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'cart_id',
        'guest_email',
        'guest_name',
        'order_number',
        'subtotal',
        'total',
        'currency',
        'status',
        'sumup_checkout_id',
        'sumup_transaction_id',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $year = date('Y');
        $lastOrder = self::whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->first();

        $sequence = 1;
        if ($lastOrder && preg_match('/OT-\d{4}-(\d+)/', $lastOrder->order_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('OT-%s-%05d', $year, $sequence);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function getCustomerEmailAttribute(): string
    {
        return $this->user?->email ?? $this->guest_email ?? '';
    }

    public function getCustomerNameAttribute(): string
    {
        if ($this->user) {
            return trim($this->user->first_name.' '.$this->user->last_name);
        }

        return $this->guest_name ?? '';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsPaid(?string $transactionId = null): void
    {
        $this->update([
            'status' => 'paid',
            'sumup_transaction_id' => $transactionId ?? $this->sumup_transaction_id,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where(function ($q) use ($email) {
            $q->whereHas('user', fn ($u) => $u->where('email', $email))
                ->orWhere('guest_email', $email);
        });
    }

    public function generateDownloadToken(): string
    {
        $token = Str::random(64);
        $this->update([
            'metadata' => array_merge($this->metadata ?? [], [
                'download_token' => $token,
                'download_token_expires_at' => now()->addDays(7)->toIso8601String(),
            ]),
        ]);

        return $token;
    }

    public function isDownloadTokenValid(string $token): bool
    {
        $storedToken = $this->metadata['download_token'] ?? null;
        $expiresAt = $this->metadata['download_token_expires_at'] ?? null;

        if (! $storedToken || $storedToken !== $token) {
            return false;
        }

        if ($expiresAt && now()->isAfter($expiresAt)) {
            return false;
        }

        return true;
    }

    /**
     * Check if order has any print items
     */
    public function hasPrintItems(): bool
    {
        return $this->items->contains(fn ($item) => $item->isPrint());
    }

    /**
     * Check if order is 100% digital
     */
    public function isFullyDigital(): bool
    {
        return ! $this->hasPrintItems();
    }

    /**
     * Get print items only
     */
    public function printItems()
    {
        return $this->items->filter(fn ($item) => $item->isPrint());
    }

    /**
     * Get digital items only
     */
    public function digitalItems()
    {
        return $this->items->filter(fn ($item) => ! $item->isPrint());
    }
}
