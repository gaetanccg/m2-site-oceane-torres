<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address_line1', 255)->nullable()->after('phone');
            $table->string('address_line2', 255)->nullable()->after('address_line1');
            $table->string('postal_code', 10)->nullable()->after('address_line2');
            $table->string('city', 100)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'postal_code', 'city']);
        });
    }
};
