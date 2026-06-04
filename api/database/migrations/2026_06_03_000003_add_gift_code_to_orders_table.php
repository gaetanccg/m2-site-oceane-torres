<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('gift_code_id')->nullable()->after('cart_id')
                ->constrained('gift_codes')->nullOnDelete();
            $table->string('gift_code', 24)->nullable()->after('gift_code_id'); // snapshot du code (audit)
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_code_id');
            $table->dropColumn(['gift_code', 'discount_amount']);
        });
    }
};
