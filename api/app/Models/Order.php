<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    use CastsBooleansForPostgres, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'cart_id',
        'guest_email',
        'guest_first_name',
        'guest_last_name',
        'shipping_phone',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_postal_code',
        'shipping_city',
        'shipping_country',
        'order_number',
        'subtotal',
        'shipping_fee',
        'total',
        'currency',
        'status',
        'print_status',
        'shipped_at',
        'sumup_checkout_id',
        'sumup_transaction_id',
        'metadata',
        'paid_at',
        'cgv_accepted',
        'cgv_accepted_at',
        'cgv_version',
        'consent_ip',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cgv_accepted' => 'boolean',
            'cgv_accepted_at' => 'datetime',
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
        // Advisory lock prevents concurrent order number generation race condition
        DB::statement('SELECT pg_advisory_xact_lock(42)');

        $year = date('Y');
        $lastOrder = self::whereYear('created_at', $year)
            ->orderByRaw("CAST(SUBSTRING(order_number FROM 'OT-\\d{4}-(\\d+)') AS INTEGER) DESC NULLS LAST")
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

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
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

        return trim(($this->guest_first_name ?? '').' '.($this->guest_last_name ?? ''));
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

    /**
     * Get detailed status for admin display
     * Returns a more precise status based on order state
     */
    public function getDetailedStatusAttribute(): string
    {
        // Failed payment
        if ($this->status === 'failed') {
            return 'payment_failed';
        }

        // Paid order - check print shipping status
        if ($this->status === 'paid') {
            if ($this->hasPrintItems()) {
                if ($this->print_status === 'shipped') {
                    return 'shipped';
                }

                return 'to_ship';
            }

            return 'paid';
        }

        // Pending order - determine where customer stopped
        if ($this->status === 'pending') {
            // No checkout created yet = cart validated but payment not started
            if (is_null($this->sumup_checkout_id)) {
                return 'checkout_initiated';
            }

            // Checkout created - check if still in progress or abandoned
            $hoursSinceCreation = $this->created_at->diffInHours(now());

            if ($hoursSinceCreation < 1) {
                return 'payment_in_progress';
            }

            return 'payment_abandoned';
        }

        // Fallback to original status
        return $this->status;
    }

    /**
     * Mark prints as shipped
     */
    public function markPrintsAsShipped(): void
    {
        $this->update([
            'print_status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    /**
     * Check if prints are shipped
     */
    public function arePrintsShipped(): bool
    {
        return $this->print_status === 'shipped';
    }
}
