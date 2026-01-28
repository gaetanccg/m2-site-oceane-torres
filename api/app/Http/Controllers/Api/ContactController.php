<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactConfirmationMail;
use App\Mail\ContactFormMail;
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

        // Send notification to admin
        Mail::to(config('mail.from.address'))
            ->send(new ContactFormMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                senderPhone: $validated['phone'] ?? null,
                messageSubject: $validated['subject'],
                messageContent: $validated['message'],
            ));

        // Send confirmation to user
        Mail::to($validated['email'])
            ->send(new ContactConfirmationMail(
                senderName: $validated['name'],
                messageSubject: $validated['subject'],
                messageContent: $validated['message'],
            ));

        return response()->json([
            'message' => 'Votre message a été envoyé avec succès.',
        ]);
    }
}
