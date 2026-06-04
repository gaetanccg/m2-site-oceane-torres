<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill `note` pour les bases ayant migré 000001 avant l'ajout de la colonne.
 * Garde `hasColumn` : no-op sur une base fraîche (000001 la crée déjà).
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
