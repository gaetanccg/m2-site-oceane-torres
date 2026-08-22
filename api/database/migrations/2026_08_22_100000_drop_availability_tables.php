<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'availability_slot_id')) {
                $table->dropIndex(['availability_slot_id']);
                $table->dropColumn('availability_slot_id');
            }
        });

        Schema::dropIfExists('availability_patterns');
        Schema::dropIfExists('availability_slots');
    }

    public function down(): void
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

        Schema::create('availability_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration_minutes')->default(60);
            $table->integer('repeat_every_weeks')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->uuid('availability_slot_id')->nullable();
            $table->index('availability_slot_id');
        });
    }
};
