<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_source_check');
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_source_check CHECK (source IN ('reservation', 'manual'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_source_check');
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_source_check CHECK (source IN ('reservation', 'manual', 'contact'))");
    }
};
