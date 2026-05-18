<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_sessions', function (Blueprint $table) {
            $table->text('sms_template')->nullable()->after('gallery_message');
        });
    }

    public function down(): void
    {
        Schema::table('school_sessions', function (Blueprint $table) {
            $table->dropColumn('sms_template');
        });
    }
};
