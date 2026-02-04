<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ajoute les champs de consentement CGV pour conformite RGPD
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('cgv_accepted')->default(false)->after('metadata');
            $table->timestamp('cgv_accepted_at')->nullable()->after('cgv_accepted');
            $table->string('cgv_version', 20)->default('1.0')->after('cgv_accepted_at');
            $table->string('consent_ip', 45)->nullable()->after('cgv_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cgv_accepted', 'cgv_accepted_at', 'cgv_version', 'consent_ip']);
        });
    }
};
