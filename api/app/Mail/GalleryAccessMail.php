<?php

namespace App\Mail;

use App\Models\Gallery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GalleryAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Gallery $gallery,
        public string $recipientName,
        public string $galleryUrl,
        public string $shareCode,
        public bool $isDirectLink = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre galerie photo est prête - {$this->gallery->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gallery-access',
        );
    }
}
