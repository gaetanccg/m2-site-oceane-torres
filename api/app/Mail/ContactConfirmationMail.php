<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $messageSubject,
        public string $messageContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de votre message - Oceane Torres Photographie',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-confirmation',
        );
    }
}
