<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * UserObserver etiquetait toute inscription en 'reservation', ce qui rendait le filtre
     * par source faux pour tous les comptes crees directement. Une inscription est une
     * troisieme origine, ni une reservation ni une saisie manuelle.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_source_check');
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_source_check CHECK (source IN ('reservation', 'manual', 'registration'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE clients SET source = 'manual' WHERE source = 'registration'");
        DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_source_check');
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_source_check CHECK (source IN ('reservation', 'manual'))");
    }
};
