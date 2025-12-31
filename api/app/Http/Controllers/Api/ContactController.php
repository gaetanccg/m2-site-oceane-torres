<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);

        // Send email to admin
        Mail::raw(
            "Nouveau message de contact:\n\n" .
            "Nom: {$validated['name']}\n" .
            "Email: {$validated['email']}\n" .
            "Téléphone: " . ($validated['phone'] ?? 'Non renseigné') . "\n" .
            "Sujet: {$validated['subject']}\n\n" .
            "Message:\n{$validated['message']}",
            function ($mail) use ($validated) {
                $mail->to(config('mail.from.address'))
                    ->replyTo($validated['email'], $validated['name'])
                    ->subject("Contact: {$validated['subject']}");
            }
        );

        return response()->json([
            'message' => 'Votre message a été envoyé avec succès.',
        ]);
    }
}
