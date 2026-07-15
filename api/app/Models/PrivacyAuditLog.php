<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit RGPD : trace chaque recherche / export / effacement de
 * données personnelles déclenché depuis l'administration (preuve de conformité).
 */
class PrivacyAuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'actor_user_id',
        'action',
        'subject_type',
        'subject_value',
        'affected',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'affected' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
