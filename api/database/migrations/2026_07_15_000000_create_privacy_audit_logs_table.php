<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Admin ayant déclenché l'action (set null si le compte est supprimé).
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action');              // search | export | erasure
            $table->string('subject_type')->nullable();  // email | phone | order_number
            $table->string('subject_value')->nullable();
            // Détail de l'action : compteurs par table, entités affectées, etc.
            $table->json('affected')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_audit_logs');
    }
};
