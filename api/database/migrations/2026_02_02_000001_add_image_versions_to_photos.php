<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('file_path_preview')->nullable()->after('file_path_watermark');
            $table->string('file_path_thumbnail')->nullable()->after('file_path_preview');
            $table->boolean('is_processed')->default(false)->after('file_path_thumbnail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['file_path_preview', 'file_path_thumbnail', 'is_processed']);
        });
    }
};
