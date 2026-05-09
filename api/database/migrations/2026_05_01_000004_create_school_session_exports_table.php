<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_session_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_session_id');
            $table->string('status')->default('pending');
            // pending, processing, completed, failed
            $table->boolean('include_digital')->default(false);
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('school_session_id')
                ->references('id')->on('school_sessions')
                ->onDelete('cascade');
            $table->index('school_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_session_exports');
    }
};
