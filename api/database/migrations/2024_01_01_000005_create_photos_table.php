<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gallery_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_path_web')->nullable()->comment('Optimized web version');
            $table->string('file_path_hd')->nullable()->comment('HD version for download');
            $table->string('file_path_watermark')->nullable()->comment('Watermarked version');
            $table->boolean('is_video')->default(false);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('likes_count')->default(0);
            $table->json('metadata')->nullable()->comment('EXIF data, dimensions, etc.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
