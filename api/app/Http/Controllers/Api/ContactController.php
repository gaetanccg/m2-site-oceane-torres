<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactFormMail;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'gdpr_consent' => ['required', 'accepted'],
        ], [
            'gdpr_consent.required' => 'Vous devez accepter le traitement de vos données personnelles.',
            'gdpr_consent.accepted' => 'Vous devez accepter le traitement de vos données personnelles.',
        ]);

        // RGPD: Stocker le message avec le consentement tracé
        $contactMessage = ContactMessage::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'gdpr_consent' => true,
            'gdpr_consent_at' => now(),
            'consent_ip' => $request->ip(),
            'consent_user_agent' => substr($request->userAgent() ?? '', 0, 255),
        ]);

        // Send notification to admin
        try {
            Mail::to(config('mail.from.address'))
                ->send(new ContactFormMail(
                    senderName: $validated['name'],
                    senderEmail: $validated['email'],
                    senderPhone: $validated['phone'] ?? null,
                    messageSubject: $validated['subject'],
                    messageContent: $validated['message'],
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
            Mail::to($validated['email'])
                ->send(new ContactConfirmationMail(
                    senderName: $validated['name'],
                    messageSubject: $validated['subject'],
                    messageContent: $validated['message'],
                ));
            $contactMessage->markUserEmailSent();
        } catch (\Exception $e) {
            Log::error('Failed to send contact confirmation email', [
                'contact_message_id' => $contactMessage->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Votre message a été envoyé avec succès.',
        ]);
    }
}
