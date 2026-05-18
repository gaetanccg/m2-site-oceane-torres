<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_product_types', function (Blueprint $table) {
            // null = fall back to static CartItem::requiresShipping($type) — preserves legacy behavior
            $table->boolean('requires_shipping')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_product_types', function (Blueprint $table) {
            $table->dropColumn('requires_shipping');
        });
    }
};
