<?php

namespace App\Mail;

use App\Services\Supervision\HealthSnapshot;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupervisionReportMail extends Mailable
{
    public function __construct(
        public HealthSnapshot $snapshot,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Supervision] Rapport quotidien — état : '.$this->snapshot->status->value,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supervision.report',
            with: [
                'version' => config('app.version'),
                'environment' => config('app.env'),
            ],
        );
    }
}
