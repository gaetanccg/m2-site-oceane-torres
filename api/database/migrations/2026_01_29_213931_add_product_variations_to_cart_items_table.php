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
        Schema::table('cart_items', function (Blueprint $table) {
            // Product type: digital, print_10x15, print_15x20
            $table->string('product_type')->default('digital')->after('photo_id');

            // Drop the old unique constraint
            $table->dropUnique(['cart_id', 'photo_id']);

            // Add new unique constraint including product_type
            $table->unique(['cart_id', 'photo_id', 'product_type']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Product type: digital, print_10x15, print_15x20
            $table->string('product_type')->default('digital')->after('photo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'photo_id', 'product_type']);
            $table->unique(['cart_id', 'photo_id']);
            $table->dropColumn('product_type');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('product_type');
        });
    }
};
