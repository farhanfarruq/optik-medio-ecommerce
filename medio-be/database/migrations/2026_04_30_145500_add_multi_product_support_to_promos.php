<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->json('buy_brands')->nullable()->after('buy_product_id');
            $table->json('discount_brands')->nullable()->after('discount_product_id');
        });

        Schema::create('promo_buy_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('promo_discount_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_discount_product');
        Schema::dropIfExists('promo_buy_product');
        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn(['buy_brands', 'discount_brands']);
        });
    }
};
