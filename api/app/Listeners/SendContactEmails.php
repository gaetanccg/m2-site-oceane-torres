<?php

namespace App\Listeners;

use App\Events\ContactMessageSent;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactFormMail;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendContactEmails
{
    public function handle(ContactMessageSent $event): void
    {
        $data = $event->validatedData;
        $contactMessage = $event->contactMessage;

        // Garde-fou d'idempotence : les colonnes email_sent_to_* existent pour
        // ça. Un listener rejoué (double câblage, retry de job) ne doit jamais
        // renvoyer un mail déjà parti.
        if (! $contactMessage->email_sent_to_admin) {
            $this->sendAdminEmail($contactMessage, $data);
        }

        if (! $contactMessage->email_sent_to_user) {
            $this->sendUserConfirmation($contactMessage, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendAdminEmail(ContactMessage $contactMessage, array $data): void
    {
        try {
            Mail::to(config('mail.admin_email', config('mail.from.address')))
                ->send(new ContactFormMail(
                    senderName: $data['name'],
                    senderEmail: $data['email'],
                    senderPhone: $data['phone'] ?? null,
                    messageSubject: $data['subject'],
                    messageContent: $data['message'],
                ));
            $contactMessage->markAdminEmailSent();
        } catch (\Exception $e) {
            Log::error('Failed to send contact admin email', [
                'contact_message_id' => $contactMessage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendUserConfirmation(ContactMessage $contactMessage, array $data): void
    {
        try {
            Mail::to($data['email'])
                ->send(new ContactConfirmationMail(
                    senderName: $data['name'],
                    messageSubject: $data['subject'],
                    messageContent: $data['message'],
                ));
            $contactMessage->markUserEmailSent();
        } catch (\Exception $e) {
            Log::error('Failed to send contact confirmation email', [
                'contact_message_id' => $contactMessage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
