<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasFactory, HasUuids;

    // Attributs calcules a inclure dans le JSON
    protected $appends = [
        'client_name',
        'client_email',
        'client_phone',
        'is_guest',
    ];

    protected $fillable = [
        'user_id',
        'client_id',
        'prestation_id',
        'date',
        'status',
        'notes',
        'message',
        'guest_name',
        'guest_email',
        'guest_phone',
        'date_preferences',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }

    public function clientForm(): HasOne
    {
        return $this->hasOne(ClientForm::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Accesseurs pour obtenir les infos client (user ou guest)

    /**
     * Ces trois accesseurs dependaient de relationLoaded() : la meme reservation rendait
     * le nom du compte dans une liste (ou `user` est charge) et le nom invite apres un
     * changement de statut (ou il ne l'est pas). La priorite est desormais la meme quel
     * que soit l'appelant — Eloquent charge la relation si besoin, et les endpoints de
     * liste la chargent deja.
     */
    public function getClientNameAttribute(): ?string
    {
        if ($this->user_id) {
            $nom = trim(($this->user->first_name ?? '').' '.($this->user->last_name ?? ''));
            if ($nom !== '') {
                return $nom;
            }
        }

        if (! empty($this->clientForm?->fullname)) {
            return $this->clientForm->fullname;
        }

        return $this->guest_name ?: null;
    }

    public function getClientEmailAttribute(): ?string
    {
        if ($this->user_id && ! empty($this->user?->email)) {
            return $this->user->email;
        }

        return $this->guest_email ?: null;
    }

    public function getClientPhoneAttribute(): ?string
    {
        if ($this->user_id && ! empty($this->user?->phone)) {
            return $this->user->phone;
        }

        if (! empty($this->clientForm?->phone)) {
            return $this->clientForm->phone;
        }

        return $this->guest_phone ?: null;
    }

    public function getIsGuestAttribute(): bool
    {
        return empty($this->user_id);
    }

    public function isGuestReservation(): bool
    {
        return $this->is_guest;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now());
    }
}
