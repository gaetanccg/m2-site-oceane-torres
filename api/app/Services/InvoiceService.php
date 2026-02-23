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
    /**
     * Generate an invoice for an order (idempotent)
     */
    public function generateForOrder(Order $order): Invoice
    {
        // Return existing invoice if already generated
        $existing = Invoice::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        $order->load('items');

        // Generate invoice number within a transaction (with advisory lock)
        $invoice = DB::transaction(function () use ($order) {
            $invoiceNumber = Invoice::generateInvoiceNumber();

            return Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'generated_at' => now(),
            ]);
        });

        // Generate PDF and store it
        try {
            $pdf = Pdf::loadView('pdf.invoice', [
                'order' => $order,
                'invoice' => $invoice,
            ]);

            $filePath = "invoices/{$order->id}/{$invoice->invoice_number}.pdf";
            Storage::disk('minio')->put($filePath, $pdf->output());

            $invoice->update(['file_path' => $filePath]);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', [
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $invoice;
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
            $storageService = new MinioStorageService;

            return $storageService->getSignedUrl($invoice->file_path, 3600);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice download URL', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
