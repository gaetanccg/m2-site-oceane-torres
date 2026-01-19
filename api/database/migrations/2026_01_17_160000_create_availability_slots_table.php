<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->default(60);
            $table->enum('status', ['available', 'booked', 'unavailable'])->default('available');
            $table->uuid('reservation_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
    }
};
