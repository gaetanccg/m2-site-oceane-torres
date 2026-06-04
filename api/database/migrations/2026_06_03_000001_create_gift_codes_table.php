<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 24)->unique();                       // stocké en MAJUSCULES
            $table->enum('type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('value', 10, 2);                            // euros si fixed ; 0-100 si percent
            $table->decimal('max_discount_amount', 10, 2)->nullable();  // plafond € (percent uniquement)
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable()->default(1);  // null = illimité
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();                           // interne admin, jamais exposée au client — cf. 000004
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_codes');
    }
};
