<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_codes', function (Blueprint $table) {
            $table->decimal('min_order_amount', 10, 2)->nullable()->after('max_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('gift_codes', function (Blueprint $table) {
            $table->dropColumn('min_order_amount');
        });
    }
};
