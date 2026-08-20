<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('global');   // global | subject
            $table->string('subject_type')->nullable();   // email | phone | order_number (export ciblé)
            $table->string('subject_value')->nullable();
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_exports');
    }
};
