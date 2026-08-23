<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function __construct(
        private MinioStorageService $storageService = new MinioStorageService,
    ) {}

    /**
     * Generate an invoice for an order (idempotent)
     */
    public function generateForOrder(Order $order): Invoice
    {
        $order->load('items');

        $existing = Invoice::where('order_id', $order->id)->first();

        // Une facture sans PDF est reparable : on regenere le fichier en conservant son
        // numero. En reallouer un creerait un trou dans la sequence.
        if ($existing) {
            if (! $existing->file_path) {
                $this->attachPdf($existing, $order);
            }

            return $existing;
        }

        $invoice = DB::transaction(function () use ($order) {
            return Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'generated_at' => now(),
            ]);
        });

        $this->attachPdf($invoice, $order);

        return $invoice;
    }

    /**
     * L'echec ne remonte pas : le numero est deja alloue et la commande est payee. Il est
     * journalise en error, donc visible via le canal Sentry, et `supervision:alert`
     * signale les factures restees sans fichier.
     */
    private function attachPdf(Invoice $invoice, Order $order): void
    {
        try {
            $pdf = Pdf::loadView('pdf.invoice', [
                'order' => $order,
                'invoice' => $invoice,
            ]);

            $filePath = "invoices/{$order->id}/{$invoice->invoice_number}.pdf";
            Storage::disk('minio')->put($filePath, $pdf->output());

            $invoice->update(['file_path' => $filePath]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate invoice PDF', [
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get a signed download URL for an invoice
     */
    public function getDownloadUrl(Invoice $invoice): ?string
    {
        if (! $invoice->file_path) {
            return null;
        }

        try {
            return $this->storageService->getSignedUrl($invoice->file_path, 3600);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice download URL', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
