<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignUuid('cart_id')->nullable()->constrained()->onDelete('set null');
            $table->string('guest_email')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'expired'])->default('pending');
            $table->string('sumup_checkout_id')->nullable();
            $table->string('sumup_transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_number');
            $table->index('status');
            $table->index('sumup_checkout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
