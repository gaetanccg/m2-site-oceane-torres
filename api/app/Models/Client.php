<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
     * Les commandes de ce client.
     *
     * Ce n'est pas une relation Eloquent parce qu'il n'existe aucune clef entre `clients` et
     * `orders` : une commande porte soit `user_id` (client connecte), soit `guest_email`
     * (achat invite). Sur les 211 commandes en base au 30/08/2026, 41 seulement ont un
     * `user_id` — rattacher par la seule clef aurait ignore les quatre cinquiemes.
     *
     * Poser une vraie colonne `orders.client_id` releve de D2 ; en attendant, c'est ici le
     * seul endroit qui sait relier les deux.
     */
    public function ordersQuery(): Builder
    {
        $criteres = array_filter([
            'user_id' => $this->user_id,
            'guest_email' => $this->email,
        ]);

        if ($criteres === []) {
            return Order::query()->whereRaw('1 = 0');
        }

        return Order::query()->where(function ($requete) use ($criteres) {
            foreach ($criteres as $colonne => $valeur) {
                $requete->orWhere($colonne, $valeur);
            }
        });
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
     * Total reellement paye par le client.
     *
     * Ne comptait que les paiements de RESERVATIONS. Or la table `reservations` est vide et
     * le seul `Payment::create` du depot rattache le paiement a `order_id` : la fiche client
     * affichait donc « Total verse : 0,00 € » pour tout le monde, en permanence.
     *
     * Les deux sources sont comptees, les reservations gardant leur place pour le jour ou
     * elles serviront.
     */
    public function getTotalPaidAttribute(): float
    {
        $desCommandes = Payment::query()
            ->where('status', 'completed')
            ->whereIn('order_id', $this->ordersQuery()->select('id'))
            ->sum('amount');

        $desReservations = Payment::query()
            ->where('status', 'completed')
            ->whereIn('reservation_id', $this->reservations()->select('id'))
            ->sum('amount');

        return (float) ($desCommandes + $desReservations);
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
