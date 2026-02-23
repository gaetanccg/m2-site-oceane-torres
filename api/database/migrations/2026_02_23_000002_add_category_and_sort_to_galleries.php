<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->uuid('event_category_id')->nullable()->after('type');
            $table->integer('sort_order')->default(0)->after('event_category_id');

            $table->foreign('event_category_id')
                ->references('id')
                ->on('event_categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropForeign(['event_category_id']);
            $table->dropColumn(['event_category_id', 'sort_order']);
        });
    }
};
