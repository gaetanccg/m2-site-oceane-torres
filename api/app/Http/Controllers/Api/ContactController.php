<?php

namespace App\Http\Controllers\Api;

use App\Events\ContactMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function send(SendContactRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        ContactMessageSent::dispatch($contactMessage, $validated);

        return response()->json([
            'message' => 'Votre message a été envoyé avec succès.',
        ]);
    }
}
