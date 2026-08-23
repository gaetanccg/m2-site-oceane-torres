<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class RepairInvoicePdfs extends Command
{
    protected $signature = 'invoices:repair-pdf {--dry-run : Lister sans régénérer}';

    protected $description = 'Régénère le PDF des factures qui n\'en ont pas, en conservant leur numéro.';

    public function handle(InvoiceService $invoices): int
    {
        $orphelines = Invoice::with('order.items')->whereNull('file_path')->get();

        if ($orphelines->isEmpty()) {
            $this->info('Toutes les factures ont leur PDF.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $repare = 0;

        foreach ($orphelines as $invoice) {
            if (! $invoice->order) {
                $this->warn("  {$invoice->invoice_number} · commande introuvable, ignorée");

                continue;
            }

            if ($dryRun) {
                $this->line("  [à régénérer] {$invoice->invoice_number}");

                continue;
            }

            $invoices->generateForOrder($invoice->order);

            if ($invoice->fresh()->file_path) {
                $this->line("  [régénéré] {$invoice->invoice_number}");
                $repare++;
            } else {
                $this->error("  [échec] {$invoice->invoice_number}");
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$orphelines->count()} facture(s) sans PDF."
            : "{$repare}/{$orphelines->count()} facture(s) régénérée(s).");

        return self::SUCCESS;
    }
}
