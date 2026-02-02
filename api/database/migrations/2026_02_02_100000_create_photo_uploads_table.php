<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_id')->index();
            $table->uuid('gallery_id');
            $table->string('original_filename');
            $table->enum('status', ['pending', 'uploading', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->uuid('photo_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('gallery_id')->references('id')->on('galleries')->onDelete('cascade');
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_uploads');
    }
};
