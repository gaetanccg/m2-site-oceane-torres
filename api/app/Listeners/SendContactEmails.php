<?php

namespace App\Listeners;

use App\Events\ContactMessageSent;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendContactEmails
{
    public function handle(ContactMessageSent $event): void
    {
        $data = $event->validatedData;
        $contactMessage = $event->contactMessage;

        // Send notification to admin
        try {
            Mail::to(config('mail.from.address'))
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

        // Send confirmation to user
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
