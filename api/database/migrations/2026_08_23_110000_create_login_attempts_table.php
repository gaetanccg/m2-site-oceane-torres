<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('successful');
            $table->uuid('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
            $table->index(['successful', 'created_at']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
