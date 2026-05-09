<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->uuid('school_session_id')->nullable()->after('parent_id');
            $table->foreign('school_session_id')
                ->references('id')->on('school_sessions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropForeign(['school_session_id']);
            $table->dropColumn('school_session_id');
        });
    }
};
