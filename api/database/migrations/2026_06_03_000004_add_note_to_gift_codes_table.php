<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la note interne admin aux bases où `gift_codes` a été créée AVANT que la
 * colonne soit ajoutée à la migration de création (000001).
 *
 * Garde `hasColumn` : sur une base fraîche, 000001 a déjà créé la colonne → ce
 * ALTER ne fait rien (pas de doublon). Sur une base déjà migrée → il l'ajoute.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gift_codes', 'note')) {
            Schema::table('gift_codes', function (Blueprint $table) {
                $table->text('note')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gift_codes', 'note')) {
            Schema::table('gift_codes', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
