<?php

namespace App\Mail;

use App\Models\Prestation;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReservationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public Prestation $prestation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nouvelle demande de réservation - {$this->reservation->guest_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-request',
        );
    }
}
