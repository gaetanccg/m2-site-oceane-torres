<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Client extends Model
{
    use CastsBooleansForPostgres, HasFactory, HasUuids;

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
     * Export des données RGPD (Article 15 — droit d'accès)
     */
    public function gdprExport(): array
    {
        $this->load(['reservations.prestation', 'reservations.payments']);

        $email = $this->email;

        // Commandes liées par user_id ou guest_email
        $orders = Order::forEmail($email)->with('items')->get();

        // Messages de contact par email
        $contacts = ContactMessage::where('email', $email)->get();

        // Download logs des galeries du client
        $downloadLogs = collect();
        if ($this->user_id) {
            $galleryIds = Gallery::where('user_id', $this->user_id)->pluck('id');
            if ($galleryIds->isNotEmpty()) {
                $downloadLogs = DownloadLog::whereIn('gallery_id', $galleryIds)->get();
            }
        }

        return [
            'personal_data' => [
                'name' => $this->name,
                'email' => $email,
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
            'orders' => $orders->map(fn ($o) => [
                'order_number' => $o->order_number,
                'status' => $o->status,
                'total' => $o->total,
                'currency' => $o->currency,
                'items_count' => $o->items->count(),
                'created_at' => $o->created_at->toIso8601String(),
                'paid_at' => $o->paid_at?->toIso8601String(),
            ])->toArray(),
            'contact_messages' => $contacts->map(fn ($c) => [
                'subject' => $c->subject,
                'message' => $c->message,
                'created_at' => $c->created_at->toIso8601String(),
            ])->toArray(),
            'download_logs' => $downloadLogs->map(fn ($d) => [
                'photo_id' => $d->photo_id,
                'gallery_id' => $d->gallery_id,
                'ip_address' => $d->ip_address,
                'downloaded_at' => $d->downloaded_at?->toIso8601String(),
            ])->toArray(),
            'exported_at' => now()->toIso8601String(),
        ];
    }
}
