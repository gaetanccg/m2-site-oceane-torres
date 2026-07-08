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
            // Pre-generated clean (no-watermark) thumbnail stored on MinIO, served
            // via a direct signed URL for downloadable galleries — mirrors the fast
            // path used by watermarked derivatives. Only populated for downloadable
            // photos.
            $table->string('file_path_thumbnail_clean')->nullable()->after('file_path_thumbnail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('file_path_thumbnail_clean');
        });
    }
};
