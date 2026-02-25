<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pack_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gallery_product_type_id');
            $table->foreign('gallery_product_type_id')
                ->references('id')->on('gallery_product_types')
                ->onDelete('cascade');
            $table->integer('min_quantity');
            $table->decimal('unit_price', 8, 2);
            $table->timestamps();

            $table->unique(['gallery_product_type_id', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_tiers');
    }
};
