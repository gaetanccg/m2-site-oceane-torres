<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('share_code', 6)->unique()->nullable()->after('access_token');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->boolean('is_downloadable')->default(false)->after('likes_count');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('share_code');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('is_downloadable');
        });
    }
};
