<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80)->index();
            // product_viewed, add_to_cart, checkout_started, shipping_selected,
            // payment_selected, order_created, payment_success, order_cancelled,
            // complaint_created, return_requested, search_no_result, checkout_failed
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable()->index();
            $table->json('payload')->nullable();
            // payload berisi data kontekstual: product_id, order_id, amount, reason, dll
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_events');
    }
};
