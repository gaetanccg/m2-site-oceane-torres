<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Rendre user_id nullable pour les demandes sans compte
            $table->uuid('user_id')->nullable()->change();

            // Champs guest pour les demandes sans compte
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');

            // Preferences de dates (texte libre)
            $table->text('date_preferences')->nullable()->after('message');

            // Lien vers le creneau confirme
            $table->uuid('availability_slot_id')->nullable()->after('date_preferences');

            $table->index('availability_slot_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['availability_slot_id']);
            $table->dropColumn([
                'guest_name',
                'guest_email',
                'guest_phone',
                'date_preferences',
                'availability_slot_id',
            ]);
        });
    }
};
