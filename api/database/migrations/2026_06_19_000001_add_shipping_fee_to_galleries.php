<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            // null = fall back to config('shop.shipping_fee_print') — preserves legacy behavior
            $table->decimal('shipping_fee', 8, 2)->nullable()->after('class_name');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};
