<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purge définitive des données comptables au-delà de la durée légale de
 * conservation (commandes / factures / paiements). Ferme la boucle de
 * l'effacement RGPD : ces enregistrements sont CONSERVÉS pendant la rétention,
 * puis supprimés ici une fois expirés.
 */
class PurgeExpiredPrivacyData extends Command
{
    protected $signature = 'privacy:purge-expired {--years=10 : Durée de conservation en années} {--dry-run : Affiche sans supprimer}';

    protected $description = 'Supprime les commandes/factures/paiements au-delà de la durée légale de conservation.';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $cutoff = now()->subYears($years);

        $orderIds = Order::where('created_at', '<', $cutoff)->pluck('id');
        $invoices = Invoice::whereIn('order_id', $orderIds)->get(['id', 'file_path']);

        $this->info("Seuil : commandes créées avant {$cutoff->toDateString()} ({$years} ans).");
        $this->info("À purger : {$orderIds->count()} commande(s), {$invoices->count()} facture(s).");

        if ($orderIds->isEmpty()) {
            $this->comment('Rien à purger.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry-run : aucune suppression effectuée.');

            return self::SUCCESS;
        }

        // PDF de factures sur MinIO.
        $minio = Storage::disk('minio');
        foreach ($invoices as $invoice) {
            if ($invoice->file_path) {
                try {
                    if ($minio->exists($invoice->file_path)) {
                        $minio->delete($invoice->file_path);
                    }
                } catch (\Throwable) {
                    // Ignoré : fichier absent/illisible.
                }
            }
        }

        Payment::whereIn('order_id', $orderIds)->delete();
        // La suppression des commandes cascade sur invoices + order_items (FK DB).
        $deleted = Order::whereIn('id', $orderIds)->delete();

        $this->info("Purge terminée : {$deleted} commande(s) supprimée(s).");

        return self::SUCCESS;
    }
}
