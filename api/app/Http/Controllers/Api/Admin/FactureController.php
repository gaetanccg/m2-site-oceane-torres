<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FactureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Facture::with(['user', 'reservation']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $factures = $query->latest()->paginate(20);

        return response()->json($factures);
    }

    public function show(Facture $facture): JsonResponse
    {
        return response()->json([
            'facture' => $facture->load(['user', 'reservation', 'payment']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reservation_id' => ['nullable', 'exists:reservations,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $taxAmount = $validated['tax_amount'] ?? 0;
        $totalAmount = $validated['amount'] + $taxAmount;

        $facture = Facture::create([
            ...$validated,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => 'draft',
        ]);

        return response()->json([
            'facture' => $facture,
            'message' => 'Facture créée avec succès.',
        ], 201);
    }

    public function update(Request $request, Facture $facture): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:draft,sent,paid,cancelled'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if (isset($validated['amount']) || isset($validated['tax_amount'])) {
            $amount = $validated['amount'] ?? $facture->amount;
            $taxAmount = $validated['tax_amount'] ?? $facture->tax_amount;
            $validated['total_amount'] = $amount + $taxAmount;
        }

        $facture->update($validated);

        return response()->json([
            'facture' => $facture->fresh(),
            'message' => 'Facture mise à jour.',
        ]);
    }

    public function destroy(Facture $facture): JsonResponse
    {
        if ($facture->status === 'paid') {
            return response()->json([
                'message' => 'Impossible de supprimer une facture payée.',
            ], 422);
        }

        $facture->delete();

        return response()->json([
            'message' => 'Facture supprimée.',
        ]);
    }

    public function downloadPdf(Facture $facture): JsonResponse
    {
        // TODO: Generate and return PDF
        return response()->json([
            'pdf_url' => $facture->pdf_path,
            'message' => 'PDF généré.',
        ]);
    }

    public function send(Facture $facture): JsonResponse
    {
        $user = $facture->user;

        // TODO: Generate PDF and attach to email
        Mail::raw(
            "Bonjour {$user->full_name},\n\n" .
            "Veuillez trouver ci-joint votre facture n°{$facture->invoice_number}.\n\n" .
            "Montant: {$facture->total_amount}€\n" .
            "Date d'échéance: " . ($facture->due_date?->format('d/m/Y') ?? 'Non définie') . "\n\n" .
            "Cordialement,\nOcéane Torres Photographie",
            function ($mail) use ($user, $facture) {
                $mail->to($user->email)
                    ->subject("Facture n°{$facture->invoice_number}");
            }
        );

        $facture->update(['status' => 'sent']);

        return response()->json([
            'message' => 'Facture envoyée par email.',
        ]);
    }
}
