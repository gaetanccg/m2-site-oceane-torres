<?php

namespace App\Services\Privacy;

use App\Models\PrivacyAuditLog;
use Illuminate\Http\Request;

/**
 * Écrit les entrées du journal d'audit RGPD. Centralisé ici pour que toute
 * action (search / export / erasure) soit tracée de façon uniforme.
 */
class PrivacyAuditLogger
{
    /**
     * @param  array<string, mixed>  $affected  Détail (compteurs par table, ids…)
     */
    public function record(
        string $action,
        ?string $subjectType,
        ?string $subjectValue,
        array $affected,
        ?Request $request = null,
    ): PrivacyAuditLog {
        return PrivacyAuditLog::create([
            'actor_user_id' => $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_value' => $subjectValue,
            'affected' => $affected,
            'ip_address' => $request?->ip(),
        ]);
    }
}
