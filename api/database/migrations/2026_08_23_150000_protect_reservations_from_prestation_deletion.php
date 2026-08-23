<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La cascade effaçait tout l'historique des reservations d'une prestation supprimee.
     * RESTRICT plutot que SET NULL : `is_active` existe deja pour retirer une prestation
     * du catalogue sans perdre son historique.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['prestation_id']);
            $table->foreign('prestation_id')->references('id')->on('prestations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['prestation_id']);
            $table->foreign('prestation_id')->references('id')->on('prestations')->cascadeOnDelete();
        });
    }
};
