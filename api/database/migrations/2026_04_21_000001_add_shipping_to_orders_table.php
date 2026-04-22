<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('subtotal');
            $table->string('shipping_phone', 20)->nullable()->after('guest_last_name');
            $table->string('shipping_address_line1', 255)->nullable()->after('shipping_phone');
            $table->string('shipping_address_line2', 255)->nullable()->after('shipping_address_line1');
            $table->string('shipping_postal_code', 10)->nullable()->after('shipping_address_line2');
            $table->string('shipping_city', 100)->nullable()->after('shipping_postal_code');
            $table->string('shipping_country', 2)->default('FR')->after('shipping_city');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_fee',
                'shipping_phone',
                'shipping_address_line1',
                'shipping_address_line2',
                'shipping_postal_code',
                'shipping_city',
                'shipping_country',
            ]);
        });
    }
};
