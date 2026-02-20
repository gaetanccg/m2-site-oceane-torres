<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gallery_product_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('product_type'); // digital, print_10x15, print_15x20
            $table->boolean('is_enabled')->default(true);
            $table->decimal('price', 8, 2)->nullable(); // null = use default from CartItem::PRODUCT_TYPES
            $table->timestamps();

            $table->unique(['gallery_id', 'product_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_product_types');
    }
};
