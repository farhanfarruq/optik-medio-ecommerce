<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Diskon dari level member (persentase diterapkan ke subtotal)
            $table->decimal('level_discount_amount', 15, 2)->default(0)->after('promo_discount_amount');
            // Poin yang digunakan untuk redeem diskon (jika belum ada)
            if (!Schema::hasColumn('orders', 'loyalty_points_used')) {
                $table->unsignedInteger('loyalty_points_used')->default(0)->after('level_discount_amount');
            }
            // Nilai rupiah dari poin yang di-redeem
            $table->decimal('loyalty_discount_amount', 15, 2)->default(0)->after('loyalty_points_used');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['level_discount_amount', 'loyalty_discount_amount']);
        });
    }
};
