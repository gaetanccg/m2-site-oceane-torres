<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une commande ne peut porter qu'UNE facture.
 *
 * `invoices.order_id` n'avait qu'un index. `InvoiceService::generateForOrder` verifiait
 * l'existence par un `where(...)->first()` HORS transaction, puis creait — et trois appelants
 * peuvent se declencher en concurrence : le webhook SumUp, le polling du front et l'action
 * admin. Le verrou consultatif protegeait l'attribution du NUMERO, pas l'existence de la
 * facture : deux factures numerotees pour une seule vente etaient possibles.
 *
 * La contrainte d'unicite est la seule garantie qui tienne — un verrou applicatif ne peut pas
 * empecher deux insertions concurrentes de lignes qui n'existent pas encore.
 */
return new class extends Migration
{
    public function up(): void
    {
        $doublons = DB::table('invoices')
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('count(*) > 1')
            ->pluck('order_id');

        if ($doublons->isNotEmpty()) {
            throw new RuntimeException(
                'Migration refusee : '.$doublons->count().' commande(s) portent deja plusieurs '
                ."factures (\n  ".$doublons->implode("\n  ")."\n).\n"
                .'Une facture ne se supprime pas sans decision comptable : trancher a la main '
                .'laquelle fait foi, emettre un avoir pour l\'autre, puis relancer.'
            );
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->index('order_id');
        });
    }
};
