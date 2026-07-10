<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->enum('source', ['reservation', 'manual', 'contact'])->default('manual');
            $table->boolean('gdpr_consent')->default(false);
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->timestamps();

            // Index de performance. Historiquement ajoutés par la migration
            // 2025_01_24_000002_add_performance_indexes, mais celle-ci s'exécute
            // AVANT la création de cette table sur une installation fraîche
            // (dates 2025 < 2026). On les crée donc ici ; l'autre migration les
            // saute via un garde Schema::hasTable('clients').
            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
