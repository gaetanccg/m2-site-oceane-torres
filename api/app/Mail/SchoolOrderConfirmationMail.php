<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolOrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public Order $order,
        public ?Invoice $invoice = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de commande photos scolaires - '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.school-order-confirmation',
            with: [
                'order' => $this->order,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->invoice || ! $this->invoice->file_path) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('minio', $this->invoice->file_path)
                ->as('facture_'.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
