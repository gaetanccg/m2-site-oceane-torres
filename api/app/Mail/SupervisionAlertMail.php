<?php

namespace App\Mail;

use App\Services\Supervision\AlertCatalog;
use App\Services\Supervision\HealthSnapshot;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Sans trait Queueable : cet email doit partir en synchrone, il peut annoncer
// que la file d'attente est à l'arrêt.
class SupervisionAlertMail extends Mailable
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public HealthSnapshot $snapshot,
        public array $reasons,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->reasons);
        $plural = $count > 1 ? 's' : '';

        return new Envelope(
            subject: "[Supervision] Alerte — {$count} anomalie{$plural} détectée{$plural}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supervision.alert',
            with: [
                'alerts' => array_map(fn (string $reason) => [
                    'reason' => $reason,
                    'label' => AlertCatalog::label($reason),
                    'action' => AlertCatalog::action($reason),
                ], $this->reasons),
                'version' => config('app.version'),
                'environment' => config('app.env'),
            ],
        );
    }
}
