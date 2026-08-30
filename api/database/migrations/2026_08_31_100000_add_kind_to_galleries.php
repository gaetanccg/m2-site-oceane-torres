<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un discriminant EXPLICITE sur les galeries.
 *
 * Il etait jusqu'ici eclate sur trois colonnes de deux natures : `type` pour l'evenement, la
 * NULLITE de `school_session_id` pour le scolaire, et l'absence des deux pour la cliente —
 * dont toute la specification tenait dans une double negation. Consequence : chaque lecteur
 * devait reconstituer la regle, et les gardes etaient asymetriques (EventGalleryController
 * verifiait `type !== 'event'` sur ses six methodes, GalleryController sur aucune).
 *
 * Le backfill est deterministe et verifie sur les 196 galeries :
 *   school_session_id non nul  → school   (149)
 *   type = 'event'             → event    ( 45)
 *   sinon                      → client   (  2)
 *
 * L'ordre compte : une galerie scolaire porte `type = 'private'`, donc le scolaire se teste
 * AVANT le type. `type` n'est pas retire ici — cette migration est additive, et les lecteurs
 * migrent ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('kind', 16)->nullable()->after('type');
        });

        DB::statement("UPDATE galleries SET kind = CASE
            WHEN school_session_id IS NOT NULL THEN 'school'
            WHEN type = 'event' THEN 'event'
            ELSE 'client' END");

        $orphelines = DB::table('galleries')->whereNull('kind')->count();
        if ($orphelines > 0) {
            throw new RuntimeException("Backfill incomplet : {$orphelines} galerie(s) sans nature.");
        }

        Schema::table('galleries', function (Blueprint $table) {
            $table->string('kind', 16)->nullable(false)->change();
            $table->index('kind');
        });

        DB::statement("ALTER TABLE galleries ADD CONSTRAINT galleries_kind_check
            CHECK (kind IN ('client', 'event', 'school'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_kind_check');

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
