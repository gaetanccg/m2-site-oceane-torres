<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'gdpr_consent',
        'gdpr_consent_at',
        'consent_ip',
        'consent_user_agent',
        'email_sent_to_admin',
        'email_sent_to_user',
    ];

    protected function casts(): array
    {
        return [
            'gdpr_consent' => 'boolean',
            'gdpr_consent_at' => 'datetime',
            'email_sent_to_admin' => 'boolean',
            'email_sent_to_user' => 'boolean',
        ];
    }

    /**
     * Marquer l'email admin comme envoyé
     */
    public function markAdminEmailSent(): void
    {
        $this->update(['email_sent_to_admin' => true]);
    }

    /**
     * Marquer l'email utilisateur comme envoyé
     */
    public function markUserEmailSent(): void
    {
        $this->update(['email_sent_to_user' => true]);
    }
}
