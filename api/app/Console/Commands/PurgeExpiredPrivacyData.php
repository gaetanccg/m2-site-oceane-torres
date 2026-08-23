<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\LoginAttempt;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SchoolSessionContact;
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
    protected $signature = 'privacy:purge-expired {--years=10 : Durée de conservation en années} {--login-days=90 : Conservation du journal de connexions} {--school-contact-months=3 : Conservation des contacts scolaires après clôture} {--dry-run : Affiche sans supprimer}';

    protected $description = 'Supprime les commandes/factures/paiements au-delà de la durée légale de conservation.';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $cutoff = now()->subYears($years);

        $orderIds = Order::where('created_at', '<', $cutoff)->pluck('id');
        $invoices = Invoice::whereIn('order_id', $orderIds)->get(['id', 'file_path']);

        $this->info("Seuil : commandes créées avant {$cutoff->toDateString()} ({$years} ans).");
        $this->info("À purger : {$orderIds->count()} commande(s), {$invoices->count()} facture(s).");

        // Contacts scolaires : coordonnees de parents d'eleves, conservees le temps que la
        // session vive puis trois mois. Rien a voir avec la conservation comptable.
        $seuilContacts = now()->subMonths((int) $this->option('school-contact-months'));
        $contacts = SchoolSessionContact::whereHas(
            'schoolSession',
            fn ($q) => $q->whereNotNull('closed_at')->where('closed_at', '<', $seuilContacts)
        );
        $nbContacts = $contacts->count();
        $this->info("Contacts scolaires : {$nbContacts} au-delà de la clôture + {$this->option('school-contact-months')} mois.");

        if ($nbContacts > 0 && ! $this->option('dry-run')) {
            $contacts->delete();
        }

        // Horizon distinct et independant : le journal de connexions porte email + IP, il
        // ne suit pas la conservation comptable de dix ans. Purge avant tout retour
        // anticipe, sinon l'absence de commande expiree l'empeche de tourner.
        $seuilJournal = now()->subDays((int) $this->option('login-days'));
        $tentatives = LoginAttempt::where('created_at', '<', $seuilJournal)->count();
        $this->info("Journal de connexions : {$tentatives} entrée(s) au-delà de {$seuilJournal->toDateString()}.");

        if ($tentatives > 0 && ! $this->option('dry-run')) {
            LoginAttempt::where('created_at', '<', $seuilJournal)->delete();
        }

        if ($orderIds->isEmpty()) {
            $this->comment('Aucune commande à purger.');

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

        // La purge est une obligation legale : elle doit reellement supprimer. Les
        // commandes sont en SoftDeletes et invoices.order_id est en RESTRICT — donc la
        // facture part d'abord, puis la commande en forceDelete.
        Invoice::whereIn('order_id', $orderIds)->delete();
        $deleted = Order::whereIn('id', $orderIds)->forceDelete();

        $this->info("Purge terminée : {$deleted} commande(s) supprimée(s).");

        return self::SUCCESS;
    }
}
