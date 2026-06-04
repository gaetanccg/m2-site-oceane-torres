<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Code promo. NE PAS confondre avec {@see GiftCard} (« Bons Cadeaux »),
 * qui est une carte prépayée débitée à l'usage.
 */
class GiftCode extends Model
{
    use CastsBooleansForPostgres, HasFactory, HasUuids;

    /** Sans caractères ambigus (O, 0, I, 1) : les codes sont saisis à la main. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount_amount',
        'valid_from',
        'valid_until',
        'max_uses',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'max_uses' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (GiftCode $giftCode) {
            $giftCode->code = $giftCode->code
                ? strtoupper(trim($giftCode->code))
                : self::generateUniqueCode();
        });

        static::updating(function (GiftCode $giftCode) {
            if ($giftCode->isDirty('code') && $giftCode->code) {
                $giftCode->code = strtoupper(trim($giftCode->code));
            }
        });
    }

    public static function generateUniqueCode(int $length = 6): string
    {
        do {
            $code = '';
            $max = strlen(self::CODE_ALPHABET) - 1;
            for ($i = 0; $i < $length; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $max)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Informatif (admin) — hors quota : un panier abandonné ne « brûle » jamais un code. */
    public function pendingCount(): int
    {
        return $this->orders()->where('status', 'pending')->count();
    }

    /** Seules les commandes payées consomment le quota `max_uses` (jamais pending/failed/expired). */
    public function paidCount(): int
    {
        return $this->orders()->where('status', 'paid')->count();
    }

    public function effectiveDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        if ($this->type === 'percent') {
            $discount = round($subtotal * ((float) $this->value) / 100, 2);

            if ($this->max_discount_amount !== null) {
                $discount = min($discount, (float) $this->max_discount_amount);
            }
        } else {
            $discount = (float) $this->value;
        }

        return min($discount, $subtotal);
    }
}
