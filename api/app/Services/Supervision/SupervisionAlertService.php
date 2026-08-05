<?php

namespace App\Services\Supervision;

use App\Mail\SupervisionAlertMail;
use App\Mail\SupervisionReportMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi synchrone assumé (Mail::send, jamais ->queue()) : une alerte qui annonce
 * une file d'attente en panne ne peut pas dépendre de cette même file.
 */
class SupervisionAlertService
{
    public function __construct(
        private HealthCheckService $health,
    ) {}

    /**
     * @return list<string> motifs effectivement notifiés
     */
    public function dispatchAlerts(): array
    {
        if (! config('supervision.alerts.enabled')) {
            return [];
        }

        $snapshot = $this->health->snapshot();

        if ($snapshot->isHealthy()) {
            return [];
        }

        $recipient = $this->recipient();

        if ($recipient === null) {
            Log::warning('Alerte de supervision non envoyée : aucun destinataire configuré', [
                'reasons' => $snapshot->reasons(),
            ]);

            return [];
        }

        $notifiable = array_values(array_filter(
            $snapshot->reasons(),
            fn (string $reason) => ! $this->isCoolingDown($reason),
        ));

        if ($notifiable === []) {
            return [];
        }

        Log::error('Alerte de supervision', [
            'status' => $snapshot->status->value,
            'reasons' => $notifiable,
            'checks' => array_map(
                fn (ProbeResult $result) => $result->toArray(),
                $snapshot->failing(),
            ),
        ]);

        Mail::to($recipient)->send(new SupervisionAlertMail($snapshot, $notifiable));

        // Verrous posés seulement après un envoi réussi : un échec SMTP ne fait pas
        // perdre l'alerte, elle repart au passage suivant.
        foreach ($notifiable as $reason) {
            $this->markNotified($reason);
        }

        return $notifiable;
    }

    public function sendDailyReport(): bool
    {
        if (! config('supervision.alerts.enabled')) {
            return false;
        }

        $recipient = $this->recipient();

        if ($recipient === null) {
            Log::warning('Rapport de santé non envoyé : aucun destinataire configuré');

            return false;
        }

        Mail::to($recipient)->send(new SupervisionReportMail($this->health->snapshot()));

        return true;
    }

    private function recipient(): ?string
    {
        $recipient = config('supervision.alerts.recipient')
            ?: config('mail.admin_email')
            ?: config('mail.from.address');

        return is_string($recipient) && $recipient !== '' ? $recipient : null;
    }

    private function isCoolingDown(string $reason): bool
    {
        return Cache::has($this->cooldownKey($reason));
    }

    private function markNotified(string $reason): void
    {
        Cache::put(
            $this->cooldownKey($reason),
            now()->getTimestamp(),
            now()->addMinutes((int) config('supervision.alerts.cooldown_minutes')),
        );
    }

    private function cooldownKey(string $reason): string
    {
        return "supervision:alert:{$reason}";
    }
}
