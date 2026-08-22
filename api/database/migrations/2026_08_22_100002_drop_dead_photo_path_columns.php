<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            foreach (['file_path_web', 'file_path_watermark'] as $column) {
                if (Schema::hasColumn('photos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('file_path_web')->nullable();
            $table->string('file_path_watermark')->nullable();
        });
    }
};
