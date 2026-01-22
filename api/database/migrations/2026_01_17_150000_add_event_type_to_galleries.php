<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, we need to drop and recreate the check constraint
        DB::statement('ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_type_check');
        DB::statement("ALTER TABLE galleries ADD CONSTRAINT galleries_type_check CHECK (type::text = ANY (ARRAY['public'::text, 'private'::text, 'event'::text]))");
    }

    public function down(): void
    {
        // Revert to original constraint (only if no 'event' galleries exist)
        DB::statement('ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_type_check');
        DB::statement("ALTER TABLE galleries ADD CONSTRAINT galleries_type_check CHECK (type::text = ANY (ARRAY['public'::text, 'private'::text]))");
    }
};
