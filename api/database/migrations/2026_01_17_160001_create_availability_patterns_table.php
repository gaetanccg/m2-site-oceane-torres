<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('days_of_week'); // [1,2,3,4,5] - 1=Lundi, 7=Dimanche
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration_minutes')->default(60);
            $table->integer('repeat_every_weeks')->default(1); // 1=chaque semaine, 2=toutes les 2 sem
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_patterns');
    }
};
