<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_sessions', function (Blueprint $table) {
            $table->text('gallery_message')->nullable()->after('product_types_config');
        });
    }

    public function down(): void
    {
        Schema::table('school_sessions', function (Blueprint $table) {
            $table->dropColumn('gallery_message');
        });
    }
};
