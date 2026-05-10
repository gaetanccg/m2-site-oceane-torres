<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->date('event_date')->nullable();
            $table->string('status')->default('uploading');
            $table->unsignedInteger('total_galleries')->default(0);
            $table->unsignedInteger('total_photos')->default(0);
            $table->unsignedInteger('processed_photos')->default(0);
            $table->string('batch_id')->nullable()->index();
            $table->string('zip_path')->nullable();
            $table->text('error_message')->nullable();
            $table->json('product_types_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_sessions');
    }
};
