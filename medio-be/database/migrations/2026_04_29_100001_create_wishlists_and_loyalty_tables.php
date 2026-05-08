<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        // Tambahkan kolom ke orders untuk tracking loyalty
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'loyalty_points_earned')) {
                $table->unsignedInteger('loyalty_points_earned')->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('orders', 'loyalty_points_used')) {
                $table->unsignedInteger('loyalty_points_used')->default(0)->after('loyalty_points_earned');
            }
        });

        // Tabel riwayat loyalty points
        Schema::create('loyalty_point_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points'); // bisa positif (earned) atau negatif (used)
            $table->string('type'); // 'earned' | 'redeemed' | 'adjusted'
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_logs');
        Schema::dropIfExists('wishlists');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_earned', 'loyalty_points_used']);
        });
    }
};
