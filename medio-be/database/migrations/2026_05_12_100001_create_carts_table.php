<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable()->index(); // untuk guest cart
            $table->string('status')->default('active'); // active, merged, abandoned, converted
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('abandoned_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['session_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->json('variant')->nullable();
            $table->json('prescription')->nullable();
            $table->unsignedBigInteger('lens_option_id')->nullable();
            $table->unsignedBigInteger('lens_coating_id')->nullable();
            $table->unsignedBigInteger('prescription_profile_id')->nullable();
            $table->json('configuration_snapshot')->nullable();
            $table->timestamps();

            $table->index(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
