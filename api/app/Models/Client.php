<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'notes',
        'source',
        'gdpr_consent',
        'gdpr_consent_at',
    ];

    protected $appends = [
        'total_paid',
        'reservations_count',
        'galleries_count',
    ];

    protected function casts(): array
    {
        return [
            'gdpr_consent' => 'boolean',
            'gdpr_consent_at' => 'datetime',
        ];
    }

    // ========================================================================
    // Relations
    // ========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Galeries du client (via le user associé)
     */
    public function galleries(): HasManyThrough
    {
        return $this->hasManyThrough(
            Gallery::class,
            User::class,
            'id',         // Foreign key on users table
            'user_id',    // Foreign key on galleries table
            'user_id',    // Local key on clients table
            'id'          // Local key on users table
        );
    }

    // ========================================================================
    // Accessors
    // ========================================================================

    /**
     * Total payé par le client (somme des paiements complétés de ses réservations)
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->reservations()
            ->with('payments')
            ->get()
            ->flatMap(fn ($reservation) => $reservation->payments)
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Nombre de réservations du client
     */
    public function getReservationsCountAttribute(): int
    {
        return $this->reservations()->count();
    }

    /**
     * Nombre de galeries du client (via user)
     */
    public function getGalleriesCountAttribute(): int
    {
        if ($this->user_id) {
            return Gallery::where('user_id', $this->user_id)->count();
        }

        return 0;
    }

    // ========================================================================
    // Scopes
    // ========================================================================

    /**
     * Clients créés depuis une réservation
     */
    public function scopeFromReservation($query)
    {
        return $query->where('source', 'reservation');
    }

    /**
     * Clients créés manuellement
     */
    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    /**
     * Clients créés depuis un contact
     */
    public function scopeFromContact($query)
    {
        return $query->where('source', 'contact');
    }

    /**
     * Recherche par nom ou email
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%");
        });
    }

    // ========================================================================
    // RGPD Methods
    // ========================================================================

    /**
     * Enregistre le consentement RGPD
     */
    public function recordGdprConsent(): void
    {
        $this->update([
            'gdpr_consent' => true,
            'gdpr_consent_at' => now(),
        ]);
    }

    /**
     * Export des données RGPD
     */
    public function gdprExport(): array
    {
        $this->load(['reservations.prestation', 'reservations.payments']);

        return [
            'personal_data' => [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'created_at' => $this->created_at->toIso8601String(),
            ],
            'consent' => [
                'gdpr_consent' => $this->gdpr_consent,
                'gdpr_consent_at' => $this->gdpr_consent_at?->toIso8601String(),
            ],
            'reservations' => $this->reservations->map(fn ($r) => [
                'id' => $r->id,
                'prestation' => $r->prestation?->title,
                'date' => $r->date?->toIso8601String(),
                'status' => $r->status,
                'created_at' => $r->created_at->toIso8601String(),
            ])->toArray(),
            'payments' => $this->reservations->flatMap(fn ($r) => $r->payments)->map(fn ($p) => [
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => $p->status,
                'created_at' => $p->created_at->toIso8601String(),
            ])->toArray(),
            'exported_at' => now()->toIso8601String(),
        ];
    }
}
