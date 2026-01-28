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
        'availability_slot_id',
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

    public function availabilitySlot(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySlot::class);
    }

    // Accesseurs pour obtenir les infos client (user ou guest)

    public function getClientNameAttribute(): ?string
    {
        // 1. Si user_id existe et que user est charge, utiliser les infos user
        if ($this->user_id && $this->relationLoaded('user') && $this->user) {
            $firstName = $this->user->first_name ?? '';
            $lastName = $this->user->last_name ?? '';
            $fullName = trim("{$firstName} {$lastName}");
            if (! empty($fullName)) {
                return $fullName;
            }
        }

        // 2. Si clientForm est charge et a un fullname, l'utiliser
        if ($this->relationLoaded('clientForm') && $this->clientForm && ! empty($this->clientForm->fullname)) {
            return $this->clientForm->fullname;
        }

        // 3. Sinon, utiliser guest_name (pour les demandes sans compte)
        if (! empty($this->guest_name)) {
            return $this->guest_name;
        }

        return null;
    }

    public function getClientEmailAttribute(): ?string
    {
        // 1. Si user_id existe et que user est charge, utiliser l'email user
        if ($this->user_id && $this->relationLoaded('user') && $this->user && ! empty($this->user->email)) {
            return $this->user->email;
        }

        // 2. Sinon, utiliser guest_email
        if (! empty($this->guest_email)) {
            return $this->guest_email;
        }

        return null;
    }

    public function getClientPhoneAttribute(): ?string
    {
        // 1. Si user_id existe et que user est charge, utiliser le phone user
        if ($this->user_id && $this->relationLoaded('user') && $this->user && ! empty($this->user->phone)) {
            return $this->user->phone;
        }

        // 2. Si clientForm est charge et a un phone, l'utiliser
        if ($this->relationLoaded('clientForm') && $this->clientForm && ! empty($this->clientForm->phone)) {
            return $this->clientForm->phone;
        }

        // 3. Sinon, utiliser guest_phone
        if (! empty($this->guest_phone)) {
            return $this->guest_phone;
        }

        return null;
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
