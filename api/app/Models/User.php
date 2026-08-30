<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'postal_code',
        'city',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Le jeton d'une session, portant les seules capacites du role au moment de l'emission.
     *
     * `createToken()` sans capacites emet un jeton `*`, tout-puissant sur les 148 routes.
     * Un jeton emis alors que le compte etait client ne peut desormais plus atteindre
     * l'administration si le compte est promu ensuite : la capacite est figee a l'emission.
     *
     * Portee : le chemin **Bearer** uniquement. Sous authentification par session, Sanctum
     * fournit un `TransientToken` dont `can()` renvoie toujours vrai — c'est voulu, et cela
     * signifie qu'une capacite ne filtre rien pour le front qui utilise le cookie.
     */
    public function jetonDeSession(): NewAccessToken
    {
        return $this->createToken('auth-token', [$this->isAdmin() ? 'admin' : 'client']);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
