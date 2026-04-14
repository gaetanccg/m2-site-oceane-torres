<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_first_name')->nullable()->after('guest_email');
            $table->string('guest_last_name')->nullable()->after('guest_first_name');
        });

        // Migrate existing data: split guest_name into first/last
        DB::table('orders')->whereNotNull('guest_name')->orderBy('id')->chunk(100, function ($orders) {
            foreach ($orders as $order) {
                $parts = explode(' ', trim($order->guest_name), 2);
                DB::table('orders')->where('id', $order->id)->update([
                    'guest_first_name' => $parts[0] ?? null,
                    'guest_last_name' => $parts[1] ?? null,
                ]);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('guest_email');
        });

        DB::table('orders')
            ->whereNotNull('guest_first_name')
            ->update([
                'guest_name' => DB::raw("CONCAT(COALESCE(guest_first_name, ''), ' ', COALESCE(guest_last_name, ''))"),
            ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['guest_first_name', 'guest_last_name']);
        });
    }
};
