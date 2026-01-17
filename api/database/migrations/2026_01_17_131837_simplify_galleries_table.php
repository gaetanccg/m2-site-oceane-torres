<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['expiration_at', 'cover_image', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->timestamp('expiration_at')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }
};
