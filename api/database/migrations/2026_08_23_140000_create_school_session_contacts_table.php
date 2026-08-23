<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D7 — les contacts n'etaient pas conserves : la diffusion partait d'un formulaire a
     * usage unique, donc ni reenvoi individuel ni relance des non-envoyes.
     *
     * ⚠️ Donnees personnelles de parents d'eleves : purge a closed_at + 3 mois
     * (`school-contacts:purge`), et integration a PersonalDataLocator / PersonalDataEraser.
     */
    public function up(): void
    {
        Schema::create('school_session_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gallery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('channel')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('send_attempts')->default(0);
            $table->timestamps();

            $table->index(['school_session_id', 'status']);
            $table->index('email');
            $table->index('phone');
            // Un meme destinataire n'est enregistre qu'une fois par galerie.
            $table->unique(['gallery_id', 'email']);
            $table->unique(['gallery_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_session_contacts');
    }
};
